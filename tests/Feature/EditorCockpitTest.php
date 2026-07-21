<?php

declare(strict_types=1);

use App\Actions\AssignReviewerAction;
use App\Actions\RespondToInvitationAction;
use App\Actions\SubmitReviewAction;
use App\Enums\Recommendation;
use App\Models\Journal;
use App\Models\JournalSection;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * The editorial cockpit — the screens that let an editor actually run peer review from a
 * browser. The Actions were already built and audited; these tests cover the read-only
 * cockpit that surfaces them, the private-file download, and the reviewer-pool guard.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('private');

    $this->journal = Journal::factory()->create(['slug' => 'jcdms', 'abbreviation' => 'JCDMS']);
    JournalSection::factory()->create(['journal_id' => $this->journal->id, 'name' => 'Research Article']);

    $this->author = grantRoleOn(User::factory()->create(), $this->journal, 'author');
    $this->editor = grantRoleOn(User::factory()->create(), $this->journal, 'journal-editor');
    $this->reviewer = grantRoleOn(User::factory()->create(['name' => 'Grace Hopper']), $this->journal, 'reviewer');

    $this->submission = submitManuscript($this->author, $this->journal, ['type' => 'Research Article']);
});

/* -------------------------------------------------------------------------- */

describe('the queue', function () {
    it('shows submitted manuscripts to an editor', function () {
        $response = $this->actingAs($this->editor)
            ->get("/admin/journals/{$this->journal->id}/submissions")
            ->assertOk();

        $refs = collect(pageProps($response)['submissions'])->pluck('reference');

        expect($refs)->toContain($this->submission->reference);
    });

    it('refuses the queue to someone with no editorial standing on the journal', function () {
        // The author owns a manuscript here, but that is not the same as being allowed to see
        // the whole queue — which carries every author's manuscript and, downstream, confidential
        // reviewer material.
        $this->actingAs($this->author)
            ->get("/admin/journals/{$this->journal->id}/submissions")
            ->assertForbidden();
    });

    it('filters by status', function () {
        $response = $this->actingAs($this->editor)
            ->get("/admin/journals/{$this->journal->id}/submissions?status=accepted")
            ->assertOk();

        // The one manuscript is Submitted, not Accepted, so the filtered queue is empty.
        expect(pageProps($response)['submissions'])->toBe([]);
    });
});

describe('the detail screen', function () {
    it('is editor-only — the author of the manuscript cannot open it', function () {
        // The author reads their own manuscript on their Dashboard, which is anonymised. This
        // screen exposes reviewer identities and confidential comments, so it gates on the
        // editor ability, not on ownership.
        $this->actingAs($this->author)
            ->get("/admin/submissions/{$this->submission->id}")
            ->assertForbidden();
    });

    it('gives the editor the real reviewer and the confidential comment', function () {
        $assignment = app(AssignReviewerAction::class)->execute($this->submission, $this->reviewer, $this->editor);
        app(RespondToInvitationAction::class)->execute($assignment, true, $this->reviewer);
        app(SubmitReviewAction::class)->execute(
            $assignment,
            Recommendation::MinorRevision,
            'Please expand the methods section.',
            'CONFIDENTIAL-EDITOR-NOTE: borderline, lean accept.',
            $this->reviewer,
        );

        $response = $this->actingAs($this->editor)
            ->get("/admin/submissions/{$this->submission->id}")
            ->assertOk();

        $json = pagePropsJson($response);

        // The editor CHOSE this reviewer, so they see the name — and the editor-only comment.
        expect($json)->toContain('Grace Hopper')
            ->and($json)->toContain('CONFIDENTIAL-EDITOR-NOTE')
            ->and($json)->toContain('Please expand the methods section.');
    });
});

describe('the manuscript download', function () {
    it('streams the file to an editor', function () {
        $file = $this->submission->files()->firstOrFail();

        $this->actingAs($this->editor)
            ->get("/admin/submissions/{$this->submission->id}/files/{$file->id}")
            ->assertOk();
    });

    it('refuses the download to a non-editor', function () {
        $file = $this->submission->files()->firstOrFail();

        $this->actingAs($this->author)
            ->get("/admin/submissions/{$this->submission->id}/files/{$file->id}")
            ->assertForbidden();
    });

    it('will not serve one submission\'s file under another submission\'s id', function () {
        $other = submitManuscript($this->author, $this->journal, ['type' => 'Research Article']);
        $otherFile = $other->files()->firstOrFail();

        // Pairing this submission with another submission's file must 404, not stream it.
        $this->actingAs($this->editor)
            ->get("/admin/submissions/{$this->submission->id}/files/{$otherFile->id}")
            ->assertNotFound();
    });
});

describe('editorial discussions', function () {
    it('lets an editor start a thread and reply to it', function () {
        $this->actingAs($this->editor)
            ->post("/submissions/{$this->submission->id}/discussions", [
                'subject' => 'Scope check',
                'body' => 'Is this in scope for the special issue?',
            ])
            ->assertSessionHasNoErrors();

        $discussion = $this->submission->discussions()->sole();
        expect($discussion->subject)->toBe('Scope check')
            ->and($discussion->messages()->count())->toBe(1)
            // The creator is a participant of what they started.
            ->and($discussion->participants()->whereKey($this->editor->id)->exists())->toBeTrue();

        $this->actingAs($this->editor)
            ->post("/discussions/{$discussion->id}/messages", ['body' => 'Agreed, borderline.'])
            ->assertSessionHasNoErrors();

        expect($discussion->messages()->count())->toBe(2);
    });

    it('is internal — the author cannot open a thread', function () {
        $this->actingAs($this->author)
            ->post("/submissions/{$this->submission->id}/discussions", [
                'subject' => 'Let me in',
                'body' => 'I want to see the editorial chatter.',
            ])
            ->assertForbidden();

        expect($this->submission->discussions()->count())->toBe(0);
    });

    it('only lets editorial staff be added as participants', function () {
        // The reviewer is a real user on the journal, but not editorial staff — they must not
        // be addable to an internal editors' thread (single-blind review depends on it).
        $this->actingAs($this->editor)
            ->post("/submissions/{$this->submission->id}/discussions", [
                'subject' => 'Bad add',
                'body' => 'Trying to add a reviewer.',
                'participants' => [$this->reviewer->id],
            ])
            ->assertSessionHasErrors('participants.0');
    });
});

describe('the reviewer-pool guard on invitations', function () {
    it('refuses to invite someone who is not a reviewer on this journal', function () {
        $outsider = User::factory()->create();

        $this->actingAs($this->editor)
            ->post("/submissions/{$this->submission->id}/reviewers", ['reviewer_id' => $outsider->id])
            ->assertSessionHasErrors('reviewer_id');

        expect($this->submission->fresh()->reviewRounds()->count())->toBe(0);
    });

    it('invites a reviewer who is in the pool', function () {
        $this->actingAs($this->editor)
            ->post("/submissions/{$this->submission->id}/reviewers", ['reviewer_id' => $this->reviewer->id])
            ->assertSessionHasNoErrors();

        expect($this->submission->fresh()->reviewRounds()->count())->toBe(1);
    });
});
