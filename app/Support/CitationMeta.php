<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Article;
use App\Models\ArticleAuthor;

/**
 * THE ONE PLACE THAT BUILDS THE LANDING-PAGE META TAG SET.
 *
 * This is what makes a DOI worth registering. A DOI resolves to a landing page; if that
 * page carries no machine-readable metadata, the identifier does the one job it exists
 * to do and fails at it.
 *
 * Rules encoded here, each of which is a real-world failure mode:
 *
 *  - Google Scholar reads `citation_*` (Highwire Press) tags from the RAW HTML. It does
 *    not execute JavaScript. That is why these tags are emitted by Blade in
 *    resources/views/app.blade.php and not by an Inertia <Head> — the React SSR process
 *    can die, and when it does Inertia falls back to client rendering *silently*.
 *
 *  - citation_pdf_url and citation_abstract_html_url must match the REAL routes byte for
 *    byte. Both are generated from Article::pdfUrl()/landingUrl(), which are the same
 *    accessors the router uses, so they cannot drift.
 *
 *  - One citation_author per author, IN SEQUENCE ORDER, each immediately followed by its
 *    citation_author_institution. Scholar pairs them positionally: reordering or
 *    interleaving them misattributes affiliations to the wrong people.
 *
 *  - A corporate author is emitted as a single citation_author. Omitting it entirely —
 *    which is what happens if you just loop over article_authors — leaves the editorial
 *    with no author at all, and Scholar will not index an authorless article.
 *
 *  - No tag is emitted with an empty value. `<meta name="citation_doi" content="">`
 *    is worse than omitting the tag: it asserts that the article has no DOI.
 *
 * The Crossref XML builder reads the same accessors, so what we advertise to crawlers
 * and what we deposit cannot disagree.
 *
 * @return list<array{0: string, 1: string}> ordered [name, content] pairs — a plain map
 *                                           would collapse the repeated citation_author keys into one.
 */
final class CitationMeta
{
    /** @return list<array{0: string, 1: string}> */
    public static function for(Article $article): array
    {
        $tags = [];

        $add = function (string $name, ?string $value) use (&$tags): void {
            if (filled($value)) {
                $tags[] = [$name, (string) $value];
            }
        };

        $journal = $article->journal;

        // --- Highwire Press (Google Scholar reads these) ------------------------
        $add('citation_journal_title', $journal->title);
        $add('citation_journal_abbrev', $journal->abbreviation);
        $add('citation_publisher', $journal->publisher);
        $add('citation_title', $article->title);

        foreach (self::contributors($article) as [$name, $institution, $orcid]) {
            $add('citation_author', $name);
            $add('citation_author_institution', $institution);
            $add('citation_author_orcid', $orcid);
        }

        // Scholar wants YYYY/MM/DD. A bare year is accepted but ranks worse, and an
        // unpublished article must advertise no publication date at all.
        $add('citation_publication_date', $article->published_at?->format('Y/m/d'));
        $add('citation_online_date', $article->published_at?->format('Y/m/d'));

        // Absent for continuous-publication journals, which have no volume or issue.
        // Emitting empty ones would assert "volume: nothing", which is not the same.
        $add('citation_volume', self::str($article->issue?->volume?->number));
        $add('citation_issue', self::str($article->issue?->number));
        $add('citation_firstpage', self::str($article->first_page));
        $add('citation_lastpage', self::str($article->last_page));

        $add('citation_issn', $journal->issn_online ?? $journal->issn_print);
        $add('citation_doi', $article->doi());

        $add('citation_abstract_html_url', $article->landingUrl());
        $add('citation_pdf_url', $article->hasPdf() ? $article->pdfUrl() : null);

        $add('citation_language', 'en');
        $add('citation_keywords', self::keywords($article));
        $add('citation_abstract', self::singleLine($article->abstract));

        // --- Dublin Core --------------------------------------------------------
        $add('DC.title', $article->title);

        foreach (self::contributors($article) as [$name]) {
            $add('DC.creator', $name);
        }

        $add('DC.publisher', $journal->publisher);
        $add('DC.date', $article->published_at?->format('Y-m-d'));
        $add('DC.type', $article->section?->name ?? 'Text');
        $add('DC.format', 'application/pdf');
        $add('DC.identifier', $article->doiUrl() ?? $article->landingUrl());
        $add('DC.source', $journal->title);
        $add('DC.language', 'en');
        $add('DC.description', self::singleLine($article->abstract));
        $add('DC.rights', $journal->license);

        // --- Open Graph (humans sharing links, not indexers) ---------------------
        $add('og:type', 'article');
        $add('og:title', $article->title);
        $add('og:description', self::truncate(self::singleLine($article->abstract), 200));
        $add('og:url', $article->landingUrl());
        $add('og:site_name', $journal->title);

        return $tags;
    }

    /**
     * Authors in citation order. A corporate author IS a contributor and must appear —
     * an authorless article does not get indexed.
     *
     * @return list<array{0: string, 1: ?string, 2: ?string}> [name, institution, orcid]
     */
    private static function contributors(Article $article): array
    {
        if ($article->hasCorporateAuthor()) {
            return [[(string) $article->corporate_author, $article->journal->publisher, null]];
        }

        return $article->authors
            ->sortBy('sequence')
            ->map(fn (ArticleAuthor $a) => [
                $a->fullName(),
                $a->affiliation,
                $a->orcid ? $a->orcidUrl() : null,   // Scholar wants the resolvable URL form
            ])
            ->values()
            ->all();
    }

    private static function keywords(Article $article): ?string
    {
        $keywords = $article->keywords ?? [];

        return $keywords === [] ? null : implode('; ', $keywords);
    }

    private static function str(?int $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private static function singleLine(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private static function truncate(?string $value, int $length): ?string
    {
        if (blank($value)) {
            return null;
        }

        return mb_strimwidth((string) $value, 0, $length, '…');
    }
}
