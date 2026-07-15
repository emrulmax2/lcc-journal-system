<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\ArticleStatus;
use App\Exceptions\FrozenIdentifierException;
use App\Models\Article;

/**
 * Guards the permanence of published identifiers.
 *
 * The failure this exists to prevent is quiet and common: an editor fixes a typo in a
 * published title, a slug package helpfully regenerates the slug from the new title,
 * the URL changes, and every DOI, citation and index entry pointing at the old URL now
 * 404s. Nobody notices, because the article still loads at its new address.
 *
 * So: once published, slug / sequence / doi_suffix are immutable, and editing the title
 * must not touch the slug. There is deliberately no bypass flag.
 */
final class ArticleObserver
{
    public function updating(Article $article): void
    {
        // getOriginal(): we care whether the article was ALREADY published before this
        // save. The publish action itself sets status and the identifiers in one write,
        // and must be allowed through.
        $wasFrozen = $article->getOriginal('status') instanceof ArticleStatus
            ? $article->getOriginal('status')->isFrozen()
            : in_array($article->getOriginal('status'), ['published', 'withdrawn'], true);

        if (! $wasFrozen) {
            return;
        }

        $violations = [];

        foreach (Article::FROZEN_ON_PUBLISH as $attribute) {
            if (! $article->isDirty($attribute)) {
                continue;
            }

            $from = $article->getOriginal($attribute);
            $to = $article->getAttribute($attribute);

            // Setting a value that was previously NULL is a completion, not a mutation:
            // an article published before its prefix was issued can still be given its
            // suffix later. Changing a value that already exists is what kills DOIs.
            if ($from === null) {
                continue;
            }

            if ((string) $from !== (string) $to) {
                $violations[] = "{$attribute} ('{$from}' -> '{$to}')";
            }
        }

        if ($violations !== []) {
            throw FrozenIdentifierException::forArticle($article->id, $violations);
        }
    }

    public function deleting(Article $article): void
    {
        if ($article->isFrozen()) {
            throw FrozenIdentifierException::forArticle(
                $article->id,
                ['the article itself — a published article is a permanent record and cannot be deleted, only withdrawn']
            );
        }
    }
}
