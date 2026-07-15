<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ReviewAssignment;
use App\Models\User;

/**
 * An invitation, and the report written against it, belong to exactly two parties: the
 * reviewer who owns it and the editors of the journal.
 *
 * NOT the author (single-blind: they may never learn who wrote which report, and
 * `comments_to_editor` is not theirs at all), and NOT the other reviewers on the same
 * manuscript — reviewer 1 reading reviewer 2's report before filing their own is how an
 * independent second opinion stops being independent.
 */
class ReviewAssignmentPolicy
{
    public function view(User $user, ReviewAssignment $assignment): bool
    {
        return $this->owns($user, $assignment) || $this->isEditor($user, $assignment);
    }

    /** Answering an invitation is personal. An editor cannot accept on a reviewer's behalf. */
    public function respond(User $user, ReviewAssignment $assignment): bool
    {
        return $this->owns($user, $assignment);
    }

    public function submitReport(User $user, ReviewAssignment $assignment): bool
    {
        return $this->owns($user, $assignment);
    }

    private function owns(User $user, ReviewAssignment $assignment): bool
    {
        return $assignment->reviewer_id === $user->id;
    }

    private function isEditor(User $user, ReviewAssignment $assignment): bool
    {
        $journal = $assignment->round?->submission?->journal;

        return $journal !== null && $user->can('assignReviewers', $journal);
    }
}
