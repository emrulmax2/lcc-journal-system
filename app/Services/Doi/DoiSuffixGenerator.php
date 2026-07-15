<?php

declare(strict_types=1);

namespace App\Services\Doi;

use App\Models\Article;
use App\Models\Journal;
use InvalidArgumentException;

/**
 * THE ONLY PLACE IN THE CODEBASE THAT BUILDS A DOI SUFFIX.
 *
 * Every view, export, citation format and Crossref deposit reads Article::doi(), which
 * reads the stored suffix this class produced. If you find yourself string-concatenating
 * a DOI anywhere else, that is the bug — a second construction site is how a journal
 * ends up with two different DOIs for the same article, one of which resolves.
 *
 * The pattern lives on the journal row (journals.doi_suffix_pattern), not here, so that
 * a new journal with a different house style needs no code change.
 *
 * Supported tokens:
 *   {journal}  journal slug, or its abbreviation lowercased if set
 *   {volume}   volume number      (issue-based only)
 *   {issue}    issue number       (issue-based only)
 *   {year}     publication year
 *   {seq}      sequence, zero-padded to journals.doi_sequence_padding
 *
 * Observed in the wild, and both must keep working:
 *   issue-based : "{journal}.v{volume}i{issue}.{seq}"  -> jcdms.v10i2.001
 *   continuous  : "{journal}.{year}.{seq}"             -> mrdn.2026.00412
 */
final class DoiSuffixGenerator
{
    public function generate(Article $article): string
    {
        $journal = $article->journal;

        if (! $journal instanceof Journal) {
            throw new InvalidArgumentException('Cannot generate a DOI suffix for an article with no journal.');
        }

        return $this->render($journal, [
            '{journal}' => $this->journalToken($journal),
            '{volume}' => (string) ($article->issue?->volume?->number ?? ''),
            '{issue}' => (string) ($article->issue?->number ?? ''),
            '{year}' => (string) ($article->issue?->volume?->year
                ?? $article->published_at?->year
                ?? now()->year),
            '{seq}' => $this->pad($article->sequence, $journal->doi_sequence_padding),
        ]);
    }

    /** @param  array<string, string>  $tokens */
    private function render(Journal $journal, array $tokens): string
    {
        $suffix = strtr($journal->doi_suffix_pattern, $tokens);

        // A pattern referencing {volume}/{issue} on a continuous journal — or on an
        // article not yet placed in an issue — would silently render "jcdms.vi.001",
        // which is a valid-looking string and a permanently wrong identifier. Refuse.
        if (preg_match('/\{[a-z]+\}/', $suffix, $m)) {
            throw new InvalidArgumentException(
                "DOI suffix pattern '{$journal->doi_suffix_pattern}' left '{$m[0]}' unresolved. "
                .'The article is probably missing an issue, a volume or a sequence.'
            );
        }

        if (str_contains($suffix, 'v.') || str_contains($suffix, 'i.') || str_contains($suffix, '..')) {
            throw new InvalidArgumentException(
                "DOI suffix '{$suffix}' contains an empty component. Refusing to mint a "
                .'malformed permanent identifier.'
            );
        }

        return $suffix;
    }

    private function journalToken(Journal $journal): string
    {
        $token = $journal->abbreviation ?: $journal->slug;

        // "JCD&MS" -> "jcdms". A DOI suffix may technically carry most characters, but
        // ampersands and spaces survive badly through URLs, BibTeX and email clients.
        return strtolower((string) preg_replace('/[^A-Za-z0-9]/', '', $token));
    }

    private function pad(?int $sequence, int $width): string
    {
        if ($sequence === null || $sequence < 1) {
            throw new InvalidArgumentException(
                'Cannot generate a DOI suffix without a sequence. An article must be '
                .'placed in an issue and ordered before it can be identified.'
            );
        }

        return str_pad((string) $sequence, $width, '0', STR_PAD_LEFT);
    }
}
