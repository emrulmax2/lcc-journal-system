<?php

declare(strict_types=1);

use App\Actions\AssignReviewerAction;
use App\Actions\ConvertSubmissionToArticleAction;
use App\Actions\PublishArticleAction;
use App\Actions\RecordDecisionAction;
use App\Actions\RespondToInvitationAction;
use App\Actions\SubmitReviewAction;
use App\Enums\ArticleStatus;
use App\Enums\DecisionType;
use App\Enums\Recommendation;
use App\Enums\ReviewerStatus;
use App\Enums\SubmissionStage;
use App\Enums\SubmissionStatus;
use App\Models\Article;
use App\Models\Journal;
use App\Models\JournalSection;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    Storage::fake('private');

    $this->journal = Journal::factory()->create(['slug' => 'jcdms', 'abbreviation' => 'JCDMS']);
    $this->section = JournalSection::factory()->create([
        'journal_id' => $this->journal->id,
        'name' => 'Research Article',
    ]);

    $this->author = grantRoleOn(User::factory()->create(), $this->journal, 'author');
    $this->editor = grantRoleOn(User::factory()->create(), $this->journal, 'journal-editor');
    $this->reviewerOne = grantRoleOn(User::factory()->create(['name' => 'Grace Hopper']), $this->journal, 'reviewer');
    $this->reviewerTwo = grantRoleOn(User::factory()->create(['name' => 'Katherine Johnson']), $this->journal, 'reviewer');

    $this->submission = submitManuscript($this->author, $this->journal, ['type' => 'Research Article']);
});

/* -------------------------------------------------------------------------- */

describe('the audit trail', function () {
    it('records EVERY state transition, with who and when', function () {
        // The whole point of the trail: an editorial decision challenged three years later
        // must be answerable from rows, not from anyone's memory of who invited whom.
        $assignment = app(AssignReviewerAction::class)
            ->execute($this->submission, $this->reviewerOne, $this->editor);

        app(RespondToInvitationAction::class)->execute($assignment, true, $this->reviewerOne);

        app(SubmitReviewAction::class)->execute(
            $assignment,
            Recommendation::MinorRevision,
            'The methods section needs a power calculation.',
            'Borderline, but I would publish it.',
            $this->reviewerOne,
        );

        app(RecordDecisionAction::class)->execute(
            $this->submission,
            DecisionType::Accept,
            'We are pleased to accept your manuscript.',
            $this->editor,
        );

        $events = $this->submission->fresh()->events()->pluck('event')->all();

        expect($events)->toBe([
            'submission.submitted',
            'round.opened',
            'reviewer.assigned',
            'reviewer.accepted',
            'review.submitted',
            'round.reports_complete',
            'round.closed',
            'decision.recorded',
            'submission.converted_to_article',
        ]);

        // "Who assigned that reviewer, and when."
        $assigned = $this->submission->events()->where('event', 'reviewer.assigned')->sole();

        expect($assigned->user_id)->toBe($this->editor->id)
            ->and($assigned->payload['reviewer_id'])->toBe($this->reviewerOne->id)
            ->and($assigned->payload['due_at'])->not->toBeNull()
            ->and($assigned->created_at)->not->toBeNull();
    });

    it('records a decline, because a decline is data too', function () {
        $assignment = app(AssignReviewerAction::class)
            ->execute($this->submission, $this->reviewerOne, $this->editor);

        app(RespondToInvitationAction::class)->execute($assignment, false, $this->reviewerOne);

        expect($assignment->fresh()->status)->toBe(ReviewerStatus::Declined)
            ->and($this->submission->events()->where('event', 'reviewer.declined')->exists())->toBeTrue();
    });

    it('keeps confidential comments OUT of the audit payload', function () {
        // Event payloads are exactly the sort of thing that ends up rendered in an admin
        // table one day. The report's text is not copied into one.
        $assignment = app(AssignReviewerAction::class)
            ->execute($this->submission, $this->reviewerOne, $this->editor);

        app(SubmitReviewAction::class)->execute(
            $assignment,
            Recommendation::Reject,
            'Public comments.',
            'CONFIDENTIAL-TO-EDITOR-XYZ',
            $this->reviewerOne,
        );

        $payloads = json_encode($this->submission->events()->pluck('payload')->all());

        expect($payloads)->not->toContain('CONFIDENTIAL-TO-EDITOR-XYZ')
            ->and($payloads)->not->toContain('Public comments.');
    });
});

/* -------------------------------------------------------------------------- */

describe('review invitations', function () {
    it('refuses to let an author review their own manuscript', function () {
        expect(fn () => app(AssignReviewerAction::class)
            ->execute($this->submission, $this->author, $this->editor))
            ->toThrow(ValidationException::class);
    });

    it('moves the manuscript to Under Review / Peer review on the first invitation', function () {
        app(AssignReviewerAction::class)->execute($this->submission, $this->reviewerOne, $this->editor);

        $fresh = $this->submission->fresh();

        expect($fresh->status)->toBe(SubmissionStatus::UnderReview)
            ->and($fresh->stage)->toBe(SubmissionStage::PeerReview);
    });

    it('moves it to the Decision stage only once every report is in', function () {
        $one = app(AssignReviewerAction::class)->execute($this->submission, $this->reviewerOne, $this->editor);
        $two = app(AssignReviewerAction::class)->execute($this->submission, $this->reviewerTwo, $this->editor);

        app(SubmitReviewAction::class)->execute($one, Recommendation::Accept, 'Fine.', null, $this->reviewerOne);

        expect($this->submission->fresh()->stage)->toBe(SubmissionStage::PeerReview);

        app(SubmitReviewAction::class)->execute($two, Recommendation::MinorRevision, 'Nearly.', null, $this->reviewerTwo);

        expect($this->submission->fresh()->stage)->toBe(SubmissionStage::Decision);
    });

    it('lets a reviewer accept their own invitation and nobody else\'s', function () {
        $mine = app(AssignReviewerAction::class)->execute($this->submission, $this->reviewerOne, $this->editor);

        $this->actingAs($this->reviewerOne)
            ->post("/reviews/{$mine->id}/accept")
            ->assertRedirect();

        expect($mine->fresh()->status)->toBe(ReviewerStatus::Accepted);

        // Reviewer two, answering reviewer one's invitation.
        $this->actingAs($this->reviewerTwo)
            ->post("/reviews/{$mine->id}/decline")
            ->assertForbidden();

        // Even the handling editor cannot answer on a reviewer's behalf.
        $this->actingAs($this->editor)
            ->post("/reviews/{$mine->id}/decline")
            ->assertForbidden();

        expect($mine->fresh()->status)->toBe(ReviewerStatus::Accepted);
    });
});

/* -------------------------------------------------------------------------- */

describe('reports', function () {
    it('does not let a reviewer see, or write against, another reviewer\'s report', function () {
        $one = app(AssignReviewerAction::class)->execute($this->submission, $this->reviewerOne, $this->editor);
        $two = app(AssignReviewerAction::class)->execute($this->submission, $this->reviewerTwo, $this->editor);

        app(SubmitReviewAction::class)->execute(
            $one,
            Recommendation::Reject,
            'REVIEWER-ONE-COMMENTS-TO-AUTHOR',
            'REVIEWER-ONE-COMMENTS-TO-EDITOR',
            $this->reviewerOne,
        );

        // Reviewer two cannot file a report against reviewer one's invitation — which is
        // also the endpoint that would let them overwrite it.
        $this->actingAs($this->reviewerTwo)
            ->post("/reviews/{$one->id}/report", [
                'recommendation' => 'accept',
                'comments_to_author' => 'Overwriting my colleague.',
            ])
            ->assertForbidden();

        expect(Review::count())->toBe(1);

        // And nothing of reviewer one's report reaches reviewer two through the dashboard.
        // An independent second opinion stops being independent the moment reviewer two can
        // read reviewer one's before writing their own.
        $props = pagePropsJson($this->actingAs($this->reviewerTwo)->get('/dashboard'));

        expect($props)->not->toContain('REVIEWER-ONE-COMMENTS-TO-AUTHOR')
            ->and($props)->not->toContain('REVIEWER-ONE-COMMENTS-TO-EDITOR')
            ->and($props)->not->toContain('Grace Hopper');

        // Reviewer two still sees the invitation they owe.
        $queue = pageProps($this->actingAs($this->reviewerTwo)->get('/dashboard'))['reviewQueue'];

        expect($queue)->toHaveCount(1)
            ->and($queue[0]['id'])->toBe($this->submission->reference)
            ->and($queue[0]['round'])->toBe(1);

        expect($two->fresh()->status)->toBe(ReviewerStatus::Invited);
    });

    it('refuses a second report on the same invitation', function () {
        $assignment = app(AssignReviewerAction::class)
            ->execute($this->submission, $this->reviewerOne, $this->editor);

        app(SubmitReviewAction::class)->execute($assignment, Recommendation::Accept, 'Good.', null, $this->reviewerOne);

        // Re-answering rewrites history, and an "accept" that becomes a "reject" three weeks
        // later is exactly what a challenged decision turns on.
        expect(fn () => app(SubmitReviewAction::class)
            ->execute($assignment->fresh(), Recommendation::Reject, 'Changed my mind.', null, $this->reviewerOne))
            ->toThrow(ValidationException::class);
    });
});

/* -------------------------------------------------------------------------- */

describe('decisions', function () {
    it('closes the round and refuses a second decision', function () {
        $assignment = app(AssignReviewerAction::class)
            ->execute($this->submission, $this->reviewerOne, $this->editor);

        app(SubmitReviewAction::class)->execute($assignment, Recommendation::Reject, 'No.', null, $this->reviewerOne);

        $this->actingAs($this->editor)
            ->post("/submissions/{$this->submission->id}/decision", [
                'decision' => 'reject',
                'body' => 'We will not be taking this further.',
            ])
            ->assertRedirect();

        $fresh = $this->submission->fresh();

        expect($fresh->status)->toBe(SubmissionStatus::Rejected)
            ->and($fresh->currentRound()->closed_at)->not->toBeNull()
            ->and($fresh->article_id)->toBeNull();      // a rejection mints nothing

        $this->actingAs($this->editor)
            ->post("/submissions/{$this->submission->id}/decision", [
                'decision' => 'accept',
                'body' => 'Actually, yes.',
            ])
            ->assertSessionHasErrors('submission');
    });

    it('does not let an editor of another journal decide', function () {
        $other = Journal::factory()->create(['slug' => 'other']);
        $outsider = grantRoleOn(User::factory()->create(), $other, 'journal-editor');

        $this->actingAs($outsider)
            ->post("/submissions/{$this->submission->id}/decision", [
                'decision' => 'accept',
                'body' => 'Not my journal.',
            ])
            ->assertForbidden();
    });

    it('does not let the author decide on their own manuscript', function () {
        $this->actingAs($this->author)
            ->post("/submissions/{$this->submission->id}/decision", [
                'decision' => 'accept',
                'body' => 'I accept my own paper.',
            ])
            ->assertForbidden();
    });
});

/* -------------------------------------------------------------------------- */

describe('acceptance converts the submission to an article', function () {
    it('creates a DRAFT article carrying authors, abstract, keywords and the manuscript', function () {
        app(RecordDecisionAction::class)->execute(
            $this->submission,
            DecisionType::Accept,
            'We are pleased to accept your manuscript.',
            $this->editor,
        );

        $submission = $this->submission->fresh();
        $article = $submission->article;

        expect($submission->status)->toBe(SubmissionStatus::Accepted)
            ->and($submission->stage)->toBe(SubmissionStage::Production)
            ->and($submission->article_id)->not->toBeNull();

        // DRAFT. Acceptance is an editorial decision; PUBLICATION is a separate act that
        // freezes a URL and spends money at Crossref. The accepted paper still has to pass
        // PublishArticleAction's pre-flight — it has no issue, no pages and no DOI suffix.
        expect($article->status)->toBe(ArticleStatus::Draft)
            ->and($article->published_at)->toBeNull()
            ->and($article->doi_suffix)->toBeNull()
            ->and($article->issue_id)->toBeNull()
            ->and($article->journal_id)->toBe($this->journal->id)
            ->and($article->journal_section_id)->toBe($this->section->id)
            ->and($article->title)->toBe($submission->title)
            ->and($article->abstract)->toBe($submission->abstract)
            ->and($article->keywords)->toBe(['coral', 'refugia', 'reef resilience']);

        // Authors carried across, IN ORDER, with the name split on the LAST space for
        // Crossref's given/family pair.
        $authors = $article->authors;

        expect($authors)->toHaveCount(2)
            ->and($authors[0]->given_name)->toBe('Ada Byron')
            ->and($authors[0]->family_name)->toBe('King')
            ->and($authors[0]->is_corresponding)->toBeTrue()
            ->and($authors[0]->affiliation)->toBe('London Churchill College')
            ->and($authors[1]->given_name)->toBe('Alan')
            ->and($authors[1]->family_name)->toBe('Turing');

        // The manuscript becomes the article's PDF — copied, not moved: the version the
        // reviewers actually read must still be retrievable if the decision is challenged.
        expect($article->hasPdf())->toBeTrue();
        Storage::disk('private')->assertExists($article->pdf->path);
        Storage::disk('private')->assertExists($submission->latestManuscript->path);

        expect($submission->events()->where('event', 'submission.converted_to_article')->sole()
            ->payload['article_id'])->toBe($article->id);
    });

    it('is idempotent — a retried decision does not mint a second paper', function () {
        $first = app(ConvertSubmissionToArticleAction::class)->execute($this->submission);
        $again = app(ConvertSubmissionToArticleAction::class)->execute($this->submission->fresh());

        expect($again->id)->toBe($first->id)
            ->and(Article::count())->toBe(1);
    });

    it('leaves the accepted article facing the publish gate, not published by it', function () {
        app(RecordDecisionAction::class)->execute(
            $this->submission,
            DecisionType::Accept,
            'Accepted.',
            $this->editor,
        );

        $article = $this->submission->fresh()->article;

        // The gate refuses: an issue-based journal's article has no issue, no sequence and
        // no page range yet. That refusal is the whole reason the conversion produces a
        // draft rather than a publication.
        expect(fn () => app(PublishArticleAction::class)->execute($article))
            ->toThrow(ValidationException::class);

        expect($article->fresh()->status)->toBe(ArticleStatus::Draft);
    });
});
