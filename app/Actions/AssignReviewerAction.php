<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ReviewerStatus;
use App\Enums\SubmissionStage;
use App\Enums\SubmissionStatus;
use App\Models\ReviewAssignment;
use App\Models\ReviewRound;
use App\Models\Submission;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Invite a reviewer, opening a round if one is not already open.
 *
 * "Who assigned that reviewer, and when" is the question this system exists to be able to
 * answer years after the fact — so the event is written in the same transaction as the
 * assignment, not after it.
 */
final class AssignReviewerAction
{
    /** Three weeks is the house default. The editor may override it per invitation. */
    private const DEFAULT_DUE_DAYS = 21;

    public function execute(
        Submission $submission,
        User $reviewer,
        User $editor,
        ?CarbonInterface $dueAt = null,
    ): ReviewAssignment {
        $this->guard($submission, $reviewer);

        return DB::transaction(function () use ($submission, $reviewer, $editor, $dueAt): ReviewAssignment {
            $round = $this->openRound($submission);

            $assignment = $round->assignments()->create([
                'reviewer_id' => $reviewer->id,
                'status' => ReviewerStatus::Invited,
                'invited_at' => now(),
                'due_at' => $dueAt ?? now()->addDays(self::DEFAULT_DUE_DAYS),
            ]);

            // A manuscript with a reviewer on it is under review, whatever it was before.
            $submission->forceFill([
                'status' => SubmissionStatus::UnderReview,
                'stage' => SubmissionStage::PeerReview,
            ])->save();

            $submission->recordEvent('reviewer.assigned', [
                'assignment_id' => $assignment->id,
                'review_round_id' => $round->id,
                'round_number' => $round->round_number,
                'reviewer_id' => $reviewer->id,
                'due_at' => $assignment->due_at->toIso8601String(),
            ], $editor);

            return $assignment;
        });
    }

    private function guard(Submission $submission, User $reviewer): void
    {
        if ($submission->isDraft()) {
            throw ValidationException::withMessages([
                'submission' => 'This manuscript is still a draft. Nothing goes to a reviewer until the author submits it.',
            ]);
        }

        // AN AUTHOR CANNOT REVIEW THEIR OWN PAPER. This is not a UI nicety; it is the
        // single most obvious way to corrupt a peer-review record, and the database is
        // where it has to be refused.
        if ($submission->corresponding_author_id === $reviewer->id) {
            throw ValidationException::withMessages([
                'reviewer' => 'An author cannot review their own manuscript.',
            ]);
        }
    }

    private function openRound(Submission $submission): ReviewRound
    {
        $open = $submission->openRound();

        if ($open !== null) {
            return $open;
        }

        $round = $submission->reviewRounds()->create([
            'round_number' => ((int) $submission->reviewRounds()->max('round_number')) + 1,
            'opened_at' => now(),
        ]);

        $submission->recordEvent('round.opened', [
            'review_round_id' => $round->id,
            'round_number' => $round->round_number,
        ]);

        return $round;
    }
}
