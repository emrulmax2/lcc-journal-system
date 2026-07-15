<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Article;
use App\Models\User;

class ArticlePolicy
{
    public function view(?User $user, Article $article): bool
    {
        // Published articles are public objects — that is the entire point of open access.
        if ($article->isPublished()) {
            return true;
        }

        // A draft is not. Note the controller returns 404 rather than 403 for guests:
        // a 403 confirms the article exists and can leak an embargoed title.
        return $user?->can('manageArticles', $article->journal) ?? false;
    }

    public function update(User $user, Article $article): bool
    {
        return $user->can('manageArticles', $article->journal);
    }

    /**
     * A published article can never be deleted, by anyone, including a site-admin —
     * hence the check on the article's own state before the permission check. It can
     * only be WITHDRAWN, which keeps the landing page resolving with a notice. Deleting
     * it would kill the DOI, and Crossref has no undo.
     *
     * ArticleObserver enforces the same rule at the model layer, so this cannot be
     * bypassed by a controller that forgets to authorize.
     */
    public function delete(User $user, Article $article): bool
    {
        return ! $article->isFrozen()
            && $user->can('manageArticles', $article->journal);
    }

    public function publish(User $user, Article $article): bool
    {
        return $user->can('publish', $article->journal);
    }

    public function withdraw(User $user, Article $article): bool
    {
        return $article->isPublished()
            && $user->can('publish', $article->journal);
    }
}
