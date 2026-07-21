<?php

declare(strict_types=1);

use App\Actions\AssignReviewerAction;
use App\Actions\RecordDecisionAction;
use App\Actions\RespondToInvitationAction;
use App\Actions\SubmitReviewAction;
use App\Enums\DecisionType;
use App\Enums\Recommendation;
use App\Enums\ReviewerStatus;
use App\Enums\SubmissionStatus;
use App\Models\Journal;
use App\Models\JournalSection;
use App\Models\User;
use App\Notifications\RevisionSubmittedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * Phase 5 — the parts of the lifecycle the pipeline could START but not FINISH:
 *  G2, the author's revision loop (a Minor/Major revision decision had nowhere to land), and
 *  G3, withdrawing a reviewer so a replacement can be brought in.
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

/* -------------------------------- G2: revisions --------------------------- */

describe('the revision loop', function () {
    it('lets the author upload a revision after a revise-and-resubmit decision', function () {
        // Editor asks for revisions.
        app(RecordDecisionAction::class)->execute(
            $this->submission, DecisionType::MinorRevision, 'Please expand the methods.', $this->editor
        );
        expect($this->submission->fresh()->status)->toBe(SubmissionStatus::RevisionsRequested);

        Notification::fake();

        $this->actingAs($this->author)
            ->post("/submissions/{$this->submission->id}/revision", [
                'file' => UploadedFile::fake()->create('revised.pdf', 120, 'application/pdf'),
                'note' => 'Methods section expanded as requested.',
            ])
            ->assertSessionHasNoErrors();

        $fresh = $this->submission->fresh();

        // A new version appended (the original manuscript is v1, the revision v2) and the
        // manuscript is back in active review.
        expect($fresh->status)->toBe(SubmissionStatus::UnderReview)
            ->and($fresh->files()->count())->toBe(2)
            ->and($fresh->events()->where('event', 'revision.submitted')->exists())->toBeTrue();

        // The editors were told.
        Notification::assertSentTo($this->editor, RevisionSubmittedNotification::class);
    });

    it('refuses a revision when the editor has not asked for one', function () {
        // Still just Submitted — no revision was requested.
        $this->actingAs($this->author)
            ->post("/submissions/{$this->submission->id}/revision", [
                'file' => UploadedFile::fake()->create('revised.pdf', 120, 'application/pdf'),
            ])
            ->assertForbidden();
    });

    it('does not let another author upload a revision to someone else\'s manuscript', function () {
        app(RecordDecisionAction::class)->execute(
            $this->submission, DecisionType::MajorRevision, 'Substantial work needed.', $this->editor
        );

        $stranger = grantRoleOn(User::factory()->create(), $this->journal, 'author');

        $this->actingAs($stranger)
            ->post("/submissions/{$this->submission->id}/revision", [
                'file' => UploadedFile::fake()->create('revised.pdf', 120, 'application/pdf'),
            ])
            ->assertForbidden();
    });
});

/* ------------------------------- G3: withdraw ----------------------------- */

describe('withdrawing a reviewer', function () {
    it('lets an editor withdraw an outstanding invitation, keeping the audit row', function () {
        $assignment = app(AssignReviewerAction::class)->execute($this->submission, $this->reviewer, $this->editor);

        $this->actingAs($this->editor)
            ->post("/reviews/{$assignment->id}/withdraw")
            ->assertSessionHasNoErrors();

        expect($assignment->fresh()->status)->toBe(ReviewerStatus::Withdrawn)
            ->and($this->submission->events()->where('event', 'reviewer.withdrawn')->exists())->toBeTrue();
    });

    it('does not let a non-editor withdraw an invitation', function () {
        $assignment = app(AssignReviewerAction::class)->execute($this->submission, $this->reviewer, $this->editor);

        $this->actingAs($this->author)
            ->post("/reviews/{$assignment->id}/withdraw")
            ->assertForbidden();

        expect($assignment->fresh()->status)->toBe(ReviewerStatus::Invited);
    });

    it('does not count a withdrawn reviewer as holding the round open', function () {
        // One reviewer withdrawn, one who reports: the round is complete, not stuck waiting on
        // the person who was withdrawn.
        $withdrawn = app(AssignReviewerAction::class)->execute($this->submission, $this->reviewer, $this->editor);
        $second = grantRoleOn(User::factory()->create(), $this->journal, 'reviewer');
        $active = app(AssignReviewerAction::class)->execute($this->submission, $second, $this->editor);

        $this->actingAs($this->editor)->post("/reviews/{$withdrawn->id}/withdraw")->assertSessionHasNoErrors();

        app(RespondToInvitationAction::class)->execute($active, true, $second);
        app(SubmitReviewAction::class)->execute(
            $active, Recommendation::Accept, 'Good.', null, $second
        );

        expect($active->round->fresh()->allReportsIn())->toBeTrue();
    });
});
