<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Recommendation;
use App\Enums\ReviewerStatus;
use App\Enums\SubmissionStage;
use App\Models\Review;
use App\Models\ReviewAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A reviewer files their report.
 *
 * The audit payload carries the RECOMMENDATION and nothing else from the report. The
 * comments — and `comments_to_editor` above all — stay in the `reviews` row, behind
 * ReviewAssignmentPolicy. Copying confidential text into an event payload would create a
 * second place it could leak from, and event payloads are the sort of thing that ends up
 * in an admin table one day without anyone re-reading this rule.
 */
final class SubmitReviewAction
{
    public function execute(
        ReviewAssignment $assignment,
        Recommendation $recommendation,
        string $commentsToAuthor,
        ?string $commentsToEditor,
        User $reviewer,
    ): Review {
        if (! $assignment->status->isOutstanding()) {
            throw ValidationException::withMessages([
                'report' => $assignment->status === ReviewerStatus::ReportSubmitted
                    ? 'You have already filed a report for this manuscript.'
                    : 'You declined this invitation, so there is no report to file.',
            ]);
        }

        return DB::transaction(function () use ($assignment, $recommendation, $commentsToAuthor, $commentsToEditor, $reviewer): Review {
            $review = $assignment->review()->create([
                'recommendation' => $recommendation,
                'comments_to_author' => $commentsToAuthor,
                'comments_to_editor' => $commentsToEditor,
                'submitted_at' => now(),
            ]);

            $assignment->forceFill([
                'status' => ReviewerStatus::ReportSubmitted,
                'responded_at' => $assignment->responded_at ?? now(),
                'completed_at' => now(),
            ])->save();

            $round = $assignment->round;
            $submission = $round->submission;

            $submission->recordEvent('review.submitted', [
                'assignment_id' => $assignment->id,
                'review_round_id' => $round->id,
                'reviewer_id' => $assignment->reviewer_id,
                'recommendation' => $recommendation->value,
            ], $reviewer);

            // The last report in is what moves the manuscript from "waiting on reviewers"
            // to "waiting on the editor" — which is what the Awaiting-your-decision tile
            // counts, so it has to be a real state change and not a view-layer guess.
            if ($round->fresh()->allReportsIn()) {
                $submission->forceFill(['stage' => SubmissionStage::Decision])->save();

                $submission->recordEvent('round.reports_complete', [
                    'review_round_id' => $round->id,
                    'round_number' => $round->round_number,
                ]);
            }

            return $review;
        });
    }
}
