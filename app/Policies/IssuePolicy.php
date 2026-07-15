<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Issue;
use App\Models\User;

/**
 * A PUBLISHED ISSUE IS IMMUTABLE.
 *
 * Not "discouraged" — impossible. Once published, its articles carry live DOIs and
 * printed page numbers. Adding an article shifts pagination. Removing one orphans a DOI.
 * Reordering changes which article a sequence-derived DOI suffix refers to. Each of those
 * silently invalidates every citation already made to the issue, and citations are the
 * only thing a journal actually sells.
 */
class IssuePolicy
{
    public function view(User $user, Issue $issue): bool
    {
        return $user->can('view', $issue->journal);
    }

    public function update(User $user, Issue $issue): bool
    {
        return ! $issue->isPublished()
            && $user->can('manageIssues', $issue->journal);
    }

    public function delete(User $user, Issue $issue): bool
    {
        return ! $issue->isPublished()
            && $user->can('manageIssues', $issue->journal);
    }

    /** Adding, removing or reordering articles — all forbidden once published. */
    public function manageArticles(User $user, Issue $issue): bool
    {
        return ! $issue->isPublished()
            && $user->can('manageArticles', $issue->journal);
    }

    public function publish(User $user, Issue $issue): bool
    {
        return ! $issue->isPublished()
            && $user->can('publish', $issue->journal);
    }
}
