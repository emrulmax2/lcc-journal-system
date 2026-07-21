<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SubmissionDiscussion;
use App\Models\User;

/**
 * Who may read and reply to an internal editorial discussion.
 *
 * An editor of the thread's journal may always see and join it (viewAllSubmissions), because
 * an internal editorial forum the handling editor cannot read is useless. Anyone explicitly
 * added is in too — this is the hook for adding a reviewer to one thread without giving them
 * the run of the queue.
 */
class SubmissionDiscussionPolicy
{
    public function view(User $user, SubmissionDiscussion $discussion): bool
    {
        return $user->can('viewAllSubmissions', $discussion->submission->journal)
            || $this->isParticipant($user, $discussion);
    }

    public function reply(User $user, SubmissionDiscussion $discussion): bool
    {
        return $this->view($user, $discussion);
    }

    private function isParticipant(User $user, SubmissionDiscussion $discussion): bool
    {
        return $discussion->participants()->whereKey($user->id)->exists();
    }
}
