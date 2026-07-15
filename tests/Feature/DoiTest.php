<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\Issue;
use App\Models\Journal;
use App\Models\Volume;
use App\Services\Doi\DoiSuffixGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeIssueBasedArticle(int $sequence = 1, int $volume = 10, int $issue = 2): Article
{
    $journal = Journal::factory()->create([
        'slug' => 'jcdms',
        'abbreviation' => 'JCD&MS',
        'doi_suffix_pattern' => '{journal}.v{volume}i{issue}.{seq}',
        'doi_sequence_padding' => 3,
    ]);

    $vol = Volume::factory()->create(['journal_id' => $journal->id, 'number' => $volume, 'year' => 2026]);
    $iss = Issue::factory()->create(['volume_id' => $vol->id, 'number' => $issue]);

    return Article::factory()->create([
        'journal_id' => $journal->id,
        'issue_id' => $iss->id,
        'sequence' => $sequence,
    ]);
}

it('generates the issue-based suffix pattern', function () {
    $article = makeIssueBasedArticle(sequence: 1);

    expect(app(DoiSuffixGenerator::class)->generate($article))->toBe('jcdms.v10i2.001');
});

it('zero-pads the sequence to the journal width', function () {
    expect(app(DoiSuffixGenerator::class)->generate(makeIssueBasedArticle(sequence: 7)))
        ->toBe('jcdms.v10i2.007');
});

it('strips the ampersand from an abbreviation like JCD&MS', function () {
    // "JCD&MS" must not become "jcd&ms" — an ampersand survives badly through URLs,
    // BibTeX and email clients, and a DOI is pasted into all three.
    expect(app(DoiSuffixGenerator::class)->generate(makeIssueBasedArticle()))
        ->not->toContain('&');
});

it('generates the continuous-publication suffix pattern', function () {
    $journal = Journal::factory()->continuous()->create([
        'slug' => 'meridian-marine',
        'abbreviation' => 'MRDN',
        'doi_suffix_pattern' => '{journal}.{year}.{seq}',
        'doi_sequence_padding' => 5,
    ]);

    $article = Article::factory()->continuous()->create([
        'journal_id' => $journal->id,
        'sequence' => 412,
        'published_at' => '2026-06-28',
    ]);

    expect(app(DoiSuffixGenerator::class)->generate($article))->toBe('mrdn.2026.00412');
});

it('refuses to mint a suffix when the article has no sequence', function () {
    $article = makeIssueBasedArticle();
    $article->sequence = null;

    expect(fn () => app(DoiSuffixGenerator::class)->generate($article))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses to mint a malformed suffix when an issue-based pattern has no issue', function () {
    // Would otherwise render "jcdms.vi.001" — a valid-looking string and a permanently
    // wrong identifier.
    $journal = Journal::factory()->create([
        'abbreviation' => 'JCDMS',
        'doi_suffix_pattern' => '{journal}.v{volume}i{issue}.{seq}',
    ]);

    $article = Article::factory()->continuous()->create([
        'journal_id' => $journal->id,
        'issue_id' => null,
        'sequence' => 1,
    ]);

    expect(fn () => app(DoiSuffixGenerator::class)->generate($article))
        ->toThrow(InvalidArgumentException::class);
});

it('returns no DOI at all until Crossref has issued a prefix', function () {
    // A half-formed DOI is worse than none, because it looks usable.
    $article = makeIssueBasedArticle();
    $article->journal->update(['doi_prefix' => null]);
    $article->update(['doi_suffix' => 'jcdms.v10i2.001']);

    expect($article->fresh()->doi())->toBeNull()
        ->and($article->fresh()->doiUrl())->toBeNull();
});

it('moves EVERY doi the journal owns when the prefix changes on one row', function () {
    // The acceptance test from the architecture doc. When the British Library and
    // Crossref finally issue real identifiers, go-live must be a data change, not a
    // code change. If any DOI in the system fails to move, something has hardcoded a
    // prefix and that is a bug.
    $journal = Journal::factory()->create([
        'abbreviation' => 'JCDMS',
        'doi_prefix' => null,
        'doi_suffix_pattern' => '{journal}.v{volume}i{issue}.{seq}',
    ]);
    $vol = Volume::factory()->create(['journal_id' => $journal->id, 'number' => 10]);
    $iss = Issue::factory()->create(['volume_id' => $vol->id, 'number' => 2]);

    $articles = collect(range(1, 5))->map(fn (int $i) => Article::factory()->create([
        'journal_id' => $journal->id,
        'issue_id' => $iss->id,
        'sequence' => $i,
        'doi_suffix' => sprintf('jcdms.v10i2.%03d', $i),
    ]));

    expect($articles->map->doi()->filter())->toBeEmpty();

    $journal->update(['doi_prefix' => '10.12345']);

    $dois = $articles->map(fn (Article $a) => $a->fresh()->doi())->all();

    expect($dois)->toBe([
        '10.12345/jcdms.v10i2.001',
        '10.12345/jcdms.v10i2.002',
        '10.12345/jcdms.v10i2.003',
        '10.12345/jcdms.v10i2.004',
        '10.12345/jcdms.v10i2.005',
    ]);
});

it('enforces global uniqueness on doi_suffix, not per-journal uniqueness', function () {
    // Two journals sharing a Crossref prefix would otherwise be able to mint the same
    // full DOI for two different articles.
    Article::factory()->create(['doi_suffix' => 'shared.001']);

    expect(fn () => Article::factory()->create(['doi_suffix' => 'shared.001']))
        ->toThrow(QueryException::class);
});
