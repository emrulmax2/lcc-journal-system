<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ReviewerStatus;
use App\Models\ReviewAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** A reviewer accepts or declines an invitation. Both answers are recorded; a decline is data. */
final class RespondToInvitationAction
{
    public function execute(ReviewAssignment $assignment, bool $accept, User $reviewer): ReviewAssignment
    {
        if ($assignment->status !== ReviewerStatus::Invited) {
            // Already answered. Re-answering would rewrite history — an "accepted" that
            // becomes a "declined" three weeks later is exactly the kind of thing a
            // challenged decision turns on.
            throw ValidationException::withMessages([
                'invitation' => 'This invitation has already been answered.',
            ]);
        }

        return DB::transaction(function () use ($assignment, $accept, $reviewer): ReviewAssignment {
            $assignment->forceFill([
                'status' => $accept ? ReviewerStatus::Accepted : ReviewerStatus::Declined,
                'responded_at' => now(),
            ])->save();

            $submission = $assignment->round->submission;

            $submission->recordEvent(
                $accept ? 'reviewer.accepted' : 'reviewer.declined',
                [
                    'assignment_id' => $assignment->id,
                    'review_round_id' => $assignment->review_round_id,
                    'reviewer_id' => $assignment->reviewer_id,
                ],
                $reviewer,
            );

            return $assignment;
        });
    }
}
