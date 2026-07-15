<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\ArticleAuthor;
use App\Models\Issue;
use App\Models\Journal;
use App\Models\Volume;
use App\Services\Citations\CitationFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** The real JCD&MS Research Protocol: five authors, Kabir external. */
function protocolArticle(): Article
{
    $journal = Journal::factory()->create([
        'slug' => 'jcdms',
        'abbreviation' => 'JCD&MS',
        'title' => 'Journal of Contemporary Development & Management Studies',
        'publisher' => 'London Churchill College',
        'doi_prefix' => '10.12345',
        'issn_online' => '2755-1234',
    ]);

    $volume = Volume::factory()->create(['journal_id' => $journal->id, 'number' => 10, 'year' => 2026]);
    $issue = Issue::factory()->published()->create([
        'volume_id' => $volume->id,
        'number' => 2,
        'publication_date' => '2026-03-01',
    ]);

    $article = Article::factory()->published()->create([
        'journal_id' => $journal->id,
        'issue_id' => $issue->id,
        'sequence' => 5,
        'slug' => 'transition-teaching-educational-leadership-motivations-barriers',
        'doi_suffix' => 'jcdms.v10i2.005',
        'title' => 'Research Protocol: Navigating the Transition from Teaching to Educational Leadership in Higher Education – Motivations and Barriers',
        'first_page' => 59,
        'last_page' => 79,
        'published_at' => '2026-03-01',
        'keywords' => ['Educational leadership', 'Higher education'],
        'corporate_author' => null,
    ]);

    $people = [
        ['Nick', 'Papé', '0000-0003-1395-3751'],
        ['Rahaman', 'Hasan', '0000-0003-1690-2458'],
        ['Gerry', 'Takamura', '0000-0002-3107-1874'],
        ['Russell', 'Kabir', '0000-0002-4510-0385'],
        ['Ilias', 'Mahmud', '0000-0003-2870-7715'],
    ];

    foreach ($people as $i => [$given, $family, $orcid]) {
        ArticleAuthor::factory()->create([
            'article_id' => $article->id,
            'given_name' => $given,
            'family_name' => $family,
            'orcid' => $orcid,
            'sequence' => $i + 1,
            'is_corresponding' => $i === 0,
        ]);
    }

    return $article->fresh(['authors', 'journal', 'issue.volume']);
}

/** The real JCD&MS editorial: a corporate author and ZERO personal authors. */
function corporateEditorial(): Article
{
    $article = protocolArticle();
    $article->authors()->delete();

    $article->update([
        'corporate_author' => 'Members of the Centre for Learning Innovation and Research (CLIR), London Churchill College',
        'title' => 'Organising Research in Teaching Intensive Higher Education: The Establishment of the Centre for Learning Innovation and Research (CLIR)',
        'first_page' => 8,
        'last_page' => 12,
    ]);

    return $article->fresh(['authors', 'journal', 'issue.volume']);
}

describe('Harvard', function () {
    it('formats a multi-author article with initials and an ampersand-free journal name', function () {
        $out = app(CitationFormatter::class)->harvard(protocolArticle());

        expect($out)
            ->toContain('Papé, N., Hasan, R., Takamura, G., Kabir, R. and Mahmud, I.')
            ->toContain('(2026)')
            ->toContain('10(2)')
            ->toContain('pp. 59–79')
            ->toContain('https://doi.org/10.12345/jcdms.v10i2.005');
    });

    it('puts the corporate author in the author position, not an empty slot', function () {
        // A naive implementation renders ", (2026)" here — a citation that matches nothing.
        $out = app(CitationFormatter::class)->harvard(corporateEditorial());

        expect($out)
            ->toStartWith('Members of the Centre for Learning Innovation and Research (CLIR), London Churchill College')
            ->not->toStartWith(',')
            ->toContain('pp. 8–12');
    });

    it('reduces a multi-part given name to multiple initials', function () {
        // "Mohammad Shahadat" -> "M.S.", not "M."
        $article = protocolArticle();
        $article->authors()->delete();
        ArticleAuthor::factory()->create([
            'article_id' => $article->id,
            'given_name' => 'Mohammad Shahadat',
            'family_name' => 'Hossain',
            'sequence' => 1,
        ]);

        expect(app(CitationFormatter::class)->harvard($article->fresh(['authors', 'journal', 'issue.volume'])))
            ->toContain('Hossain, M.S.');
    });
});

describe('BibTeX', function () {
    it('emits a well-formed @article entry', function () {
        $out = app(CitationFormatter::class)->bibtex(protocolArticle());

        expect($out)
            ->toStartWith('@article{')
            ->toContain('author    = {Papé, Nick and Hasan, Rahaman and Takamura, Gerry and Kabir, Russell and Mahmud, Ilias}')
            ->toContain('volume    = {10}')
            ->toContain('number    = {2}')
            ->toContain('doi       = {10.12345/jcdms.v10i2.005}')
            ->toEndWith('}');
    });

    it('uses a double hyphen for the page range, not an en-dash', function () {
        // An en-dash here renders as a literal artefact in every LaTeX document that
        // imports the entry.
        $out = app(CitationFormatter::class)->bibtex(protocolArticle());

        expect($out)->toContain('pages     = {59--79}')->not->toContain('59–79');
    });

    it('braces the corporate author so BibTeX cannot reorder it into a surname', function () {
        // Unbraced, BibTeX reads the last word as a surname and renders the entry as
        // "College, Members of the Centre ... London Churchill".
        $out = app(CitationFormatter::class)->bibtex(corporateEditorial());

        expect($out)->toContain('author    = {{Members of the Centre for Learning Innovation and Research (CLIR), London Churchill College}}');
    });
});

describe('RIS', function () {
    it('emits one A1 line per author, in sequence order, and terminates with ER', function () {
        $out = app(CitationFormatter::class)->ris(protocolArticle());
        $lines = explode("\n", $out);

        expect($lines[0])->toBe('TY  - JOUR');
        expect($out)
            ->toContain('A1  - Papé, Nick')
            ->toContain('A1  - Kabir, Russell')
            ->toContain('VL  - 10')
            ->toContain('IS  - 2')
            ->toContain('SP  - 59')
            ->toContain('EP  - 79')
            ->toContain('DO  - 10.12345/jcdms.v10i2.005');
        expect(trim($lines[count($lines) - 1]))->toBe('ER  -');

        // Author ORDER is meaningful and must survive.
        $authors = array_values(array_filter($lines, fn ($l) => str_starts_with($l, 'A1  - ')));
        expect($authors[0])->toBe('A1  - Papé, Nick')
            ->and($authors[3])->toBe('A1  - Kabir, Russell');
    });

    it('strips commas from a corporate author so importers do not split it into a surname', function () {
        $out = app(CitationFormatter::class)->ris(corporateEditorial());

        expect($out)->toContain('A1  - Members of the Centre for Learning Innovation and Research (CLIR) London Churchill College');
    });
});

it('renders no DOI in any format until a prefix has been issued', function () {
    $article = protocolArticle();
    $article->journal->update(['doi_prefix' => null]);
    $article = $article->fresh(['authors', 'journal', 'issue.volume']);

    $all = app(CitationFormatter::class)->all($article);

    expect($all['harvard'])->not->toContain('doi.org')
        ->and($all['bibtex'])->not->toContain('doi       =')
        ->and($all['ris'])->not->toContain('DO  - ');
});
