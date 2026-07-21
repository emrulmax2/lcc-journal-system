<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ReviewerStatus;
use App\Models\ReviewAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The editor withdraws an outstanding invitation — a reviewer who has gone silent, or was
 * invited in error — so a replacement can be brought in.
 *
 * The assignment is MARKED withdrawn, not deleted: "who was invited, and what became of the
 * invitation" is exactly the kind of question an integrity review asks years later, and a
 * deleted row cannot answer it. Only an outstanding invitation can be withdrawn — a submitted
 * report is a fact, not an invitation, and a decline already closed itself.
 */
final class WithdrawInvitationAction
{
    public function execute(ReviewAssignment $assignment, User $editor): void
    {
        if (! $assignment->status->isOutstanding()) {
            throw ValidationException::withMessages([
                'assignment' => 'Only an invitation still awaiting a response can be withdrawn.',
            ]);
        }

        DB::transaction(function () use ($assignment, $editor): void {
            $assignment->forceFill([
                'status' => ReviewerStatus::Withdrawn,
                'completed_at' => now(),
            ])->save();

            $assignment->round?->submission?->recordEvent('reviewer.withdrawn', [
                'assignment_id' => $assignment->id,
                'review_round_id' => $assignment->review_round_id,
                'reviewer_id' => $assignment->reviewer_id,
            ], $editor);
        });
    }
}
