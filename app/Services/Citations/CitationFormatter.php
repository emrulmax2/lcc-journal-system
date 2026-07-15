<?php

declare(strict_types=1);

namespace App\Services\Citations;

use App\Models\Article;
use App\Models\ArticleAuthor;
use Illuminate\Support\Collection;

/**
 * Harvard, BibTeX and RIS.
 *
 * The case that breaks naive implementations is the CORPORATE AUTHOR: JCD&MS Vol 10
 * No 2 Article 001 is an editorial by "Members of the Centre for Learning Innovation
 * and Research (CLIR), London Churchill College", with zero rows in article_authors.
 * Every method here handles that explicitly, because an empty author list otherwise
 * renders as ", (2026)" — a citation that cannot be matched to anything.
 *
 * All three formats read Article::doi(). None of them build a DOI string themselves.
 */
final class CitationFormatter
{
    /** Papé, N., Hasan, R. and Takamura, G. (2026) 'Title', Journal, 10(2), pp. 59–79. doi: … */
    public function harvard(Article $article): string
    {
        $parts = [];

        $parts[] = $this->harvardAuthors($article);
        $parts[] = '('.$this->year($article).')';
        $parts[] = "'".$this->stripTrailingPeriod($article->title)."',";
        $parts[] = $this->italicless($article->journal->title).',';

        if ($volumeIssue = $this->volumeIssue($article)) {
            $parts[] = $volumeIssue.',';
        }

        if ($pages = $article->pageRange()) {
            $parts[] = 'pp. '.$pages.'.';
        }

        if ($doi = $article->doiUrl()) {
            $parts[] = 'doi: '.$doi;
        }

        return $this->tidy(implode(' ', array_filter($parts)));
    }

    public function bibtex(Article $article): string
    {
        $key = $this->citationKey($article);

        $fields = [
            'author' => $this->bibtexAuthors($article),
            'title' => $this->stripTrailingPeriod($article->title),
            'journal' => $article->journal->title,
            'year' => $this->year($article),
            'volume' => $article->issue?->volume?->number,
            'number' => $article->issue?->number,
            // BibTeX page ranges use a double hyphen; an en-dash here is a rendering bug
            // in every LaTeX document that imports it.
            'pages' => $article->pageRange() ? str_replace('–', '--', $article->pageRange()) : null,
            'doi' => $article->doi(),
            'url' => $article->doiUrl() ?? $article->landingUrl(),
            'issn' => $article->journal->issn_online,
            'publisher' => $article->journal->publisher,
        ];

        $lines = ["@article{{$key},"];

        foreach ($fields as $name => $value) {
            if (blank($value)) {
                continue;
            }

            // `author` arrives pre-escaped from bibtexAuthors(), because the corporate
            // case wraps the name in protective braces — and escaping those braces here
            // would defeat the protection they exist to provide.
            $rendered = $name === 'author'
                ? (string) $value
                : $this->bibtexEscape((string) $value);

            $lines[] = sprintf('  %-9s = {%s},', $name, $rendered);
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }

    public function ris(Article $article): string
    {
        $lines = [];
        $lines[] = 'TY  - JOUR';

        if ($article->hasCorporateAuthor()) {
            // RIS has no dedicated corporate-author tag that importers agree on. A1 with
            // the full organisation name is what Zotero, Mendeley and EndNote all read
            // correctly; the comma-free string is what stops them splitting it into
            // "Members of the Centre..." as a surname and given name.
            $lines[] = 'A1  - '.$this->flattenCorporate($article->corporate_author);
        } else {
            foreach ($article->authors as $author) {
                $lines[] = 'A1  - '.$author->family_name.', '.$author->given_name;
            }
        }

        $lines[] = 'TI  - '.$this->stripTrailingPeriod($article->title);
        $lines[] = 'JO  - '.$article->journal->title;

        if ($abbrev = $article->journal->abbreviation) {
            $lines[] = 'J2  - '.$abbrev;
        }

        $lines[] = 'PY  - '.$this->year($article);

        if ($volume = $article->issue?->volume?->number) {
            $lines[] = 'VL  - '.$volume;
        }

        if ($issue = $article->issue?->number) {
            $lines[] = 'IS  - '.$issue;
        }

        if ($article->first_page !== null) {
            $lines[] = 'SP  - '.$article->first_page;
        }

        if ($article->last_page !== null) {
            $lines[] = 'EP  - '.$article->last_page;
        }

        if ($article->abstract) {
            $lines[] = 'AB  - '.$this->singleLine($article->abstract);
        }

        foreach ($article->keywords ?? [] as $keyword) {
            $lines[] = 'KW  - '.$keyword;
        }

        if ($issn = $article->journal->issn_online) {
            $lines[] = 'SN  - '.$issn;
        }

        if ($doi = $article->doi()) {
            $lines[] = 'DO  - '.$doi;
        }

        $lines[] = 'UR  - '.($article->doiUrl() ?? $article->landingUrl());
        $lines[] = 'PB  - '.$article->journal->publisher;
        $lines[] = 'ER  - ';

        return implode("\n", $lines);
    }

    /** @return array<string, string> */
    public function all(Article $article): array
    {
        return [
            'harvard' => $this->harvard($article),
            'bibtex' => $this->bibtex($article),
            'ris' => $this->ris($article),
        ];
    }

    // --- Authors ------------------------------------------------------------

    private function harvardAuthors(Article $article): string
    {
        if ($article->hasCorporateAuthor()) {
            return $this->stripTrailingPeriod((string) $article->corporate_author);
        }

        /** @var Collection<int, ArticleAuthor> $authors */
        $authors = $article->authors;

        if ($authors->isEmpty()) {
            // No personal authors and no corporate author. Rather than emit a citation
            // with an empty author slot — which silently mis-cites — say so.
            return 'Anon.';
        }

        $names = $authors->map(
            fn (ArticleAuthor $a) => $a->family_name.', '.$this->initials($a->given_name)
        )->all();

        if (count($names) === 1) {
            return $names[0];
        }

        $last = array_pop($names);

        return implode(', ', $names).' and '.$last;
    }

    /** Returns an ALREADY-ESCAPED value — see the `author` special case in bibtex(). */
    private function bibtexAuthors(Article $article): string
    {
        if ($article->hasCorporateAuthor()) {
            // Braces stop BibTeX treating the organisation name as "Firstname Lastname"
            // and reordering it into "College, London Churchill". This is the standard
            // corporate-author idiom and it is load-bearing. The name is escaped INSIDE
            // the braces so that the braces themselves survive.
            return '{'.$this->bibtexEscape((string) $article->corporate_author).'}';
        }

        if ($article->authors->isEmpty()) {
            return '{Anon.}';
        }

        return $article->authors
            ->map(fn (ArticleAuthor $a) => $this->bibtexEscape($a->family_name.', '.$a->given_name))
            ->implode(' and ');
    }

    private function initials(string $givenName): string
    {
        // "Mohammad Shahadat" -> "M.S." — Harvard wants initials, and a multi-part given
        // name must not collapse to just the first.
        $parts = preg_split('/[\s\-]+/u', trim($givenName), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode('.', array_map(
            fn (string $p) => mb_strtoupper(mb_substr($p, 0, 1)),
            $parts
        )).'.';
    }

    private function flattenCorporate(?string $name): string
    {
        // Commas inside a corporate name make RIS importers split it into surname/given.
        return trim(str_replace(',', '', (string) $name));
    }

    // --- Bits ---------------------------------------------------------------

    private function year(Article $article): string
    {
        return (string) (
            $article->published_at?->year
            ?? $article->issue?->publication_date?->year
            ?? $article->issue?->volume?->year
            ?? now()->year
        );
    }

    private function volumeIssue(Article $article): ?string
    {
        $volume = $article->issue?->volume?->number;
        $issue = $article->issue?->number;

        if ($volume === null) {
            return null;
        }

        return $issue === null ? (string) $volume : "{$volume}({$issue})";
    }

    private function citationKey(Article $article): string
    {
        $who = $article->hasCorporateAuthor()
            ? 'clir'
            : strtolower((string) ($article->authors->first()?->family_name ?? 'anon'));

        $who = preg_replace('/[^a-z0-9]/', '', $this->deaccent($who)) ?: 'anon';

        return $who.$this->year($article).substr(md5($article->slug), 0, 4);
    }

    private function bibtexEscape(string $value): string
    {
        return str_replace(['{', '}', '\\'], ['\\{', '\\}', '\\textbackslash '], $value);
    }

    private function deaccent(string $value): string
    {
        return (string) (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value);
    }

    private function italicless(string $value): string
    {
        return $value;
    }

    private function stripTrailingPeriod(string $value): string
    {
        return rtrim(trim($value), '.');
    }

    private function singleLine(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function tidy(string $value): string
    {
        return trim((string) preg_replace('/\s+([,.])/', '$1', $value));
    }
}
