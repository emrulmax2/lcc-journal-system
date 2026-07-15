<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Submission;
use App\Models\User;

/**
 * Every question here delegates the journal-scoped part to JournalPolicy, which is where
 * the Spatie team context is handled. An editor of Journal A has no standing on a
 * manuscript submitted to Journal B, and that must remain true no matter which endpoint is
 * asking.
 */
class SubmissionPolicy
{
    /** The author who owns it, or an editor of its journal. Nobody else — not even a reviewer. */
    public function view(User $user, Submission $submission): bool
    {
        return $this->owns($user, $submission)
            || $user->can('viewAllSubmissions', $submission->journal);
    }

    /** Only the author, and only while it is still a draft. Submitting is a one-way door. */
    public function update(User $user, Submission $submission): bool
    {
        return $this->owns($user, $submission) && $submission->isDraft();
    }

    public function assignReviewers(User $user, Submission $submission): bool
    {
        return $user->can('assignReviewers', $submission->journal);
    }

    public function decide(User $user, Submission $submission): bool
    {
        return $user->can('recordDecision', $submission->journal);
    }

    /**
     * THE ANONYMITY GATE. The single question SubmissionPresenter asks before it emits a
     * reviewer's name, affiliation or avatar.
     *
     * It is deliberately the same permission as inviting reviewers: the people who choose
     * the reviewers are the people who may know who they are. An author never passes this,
     * on their own manuscript or anyone else's, and neither does one reviewer about another.
     */
    public function seeReviewerIdentities(User $user, Submission $submission): bool
    {
        return $user->can('assignReviewers', $submission->journal);
    }

    private function owns(User $user, Submission $submission): bool
    {
        return $submission->corresponding_author_id === $user->id;
    }
}
