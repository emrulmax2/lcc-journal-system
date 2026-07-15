<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\ArticleAuthor;
use App\Models\Journal;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * OAI-PMH is how DOAJ, BASE, CORE and OpenAIRE pull our metadata. It is consumed
 * exclusively by machines that do not run JavaScript, and DOAJ requires it.
 */
function oaiArticle(array $overrides = []): Article
{
    $journal = Journal::factory()->withDoiPrefix()->create([
        'slug' => 'jcdms',
        'title' => 'Journal of Contemporary Development & Management Studies',
        'publisher' => 'London Churchill College',
        'license' => 'CC BY 4.0',
    ]);

    $article = Article::factory()->published()->create(array_merge([
        'journal_id' => $journal->id,
        'slug' => 'a-published-article',
        'doi_suffix' => 'jcdms.v10i2.001',
        'title' => 'A published article',
        'abstract' => 'An abstract.',
        'keywords' => ['alpha', 'beta'],
        'published_at' => '2026-03-01',
    ], $overrides));

    ArticleAuthor::factory()->create([
        'article_id' => $article->id,
        'given_name' => 'Nick', 'family_name' => 'Papé', 'sequence' => 1,
    ]);

    return $article->fresh();
}

it('answers Identify', function () {
    oaiArticle();

    $xml = $this->get('/oai?verb=Identify')->assertOk()->getContent();

    expect($xml)->toContain('<protocolVersion>2.0</protocolVersion>')
        ->toContain('<repositoryName>')
        // We never delete a published record — it can only be withdrawn, because deleting
        // it would kill the DOI.
        ->toContain('<deletedRecord>no</deletedRecord>');
});

it('lists a published record in oai_dc, with the corporate author as a creator', function () {
    $article = oaiArticle();
    $article->authors()->delete();
    $article->update(['corporate_author' => 'Members of the CLIR, London Churchill College']);

    $xml = $this->get('/oai?verb=ListRecords&metadataPrefix=oai_dc')->assertOk()->getContent();

    // An authorless record is rejected by DOAJ. Looping over article_authors alone would
    // produce exactly that for the editorial.
    expect($xml)->toContain('<dc:creator>Members of the CLIR, London Churchill College</dc:creator>');
});

it('exposes the DOI and the landing page as identifiers', function () {
    oaiArticle();

    $xml = $this->get('/oai?verb=ListRecords&metadataPrefix=oai_dc')->getContent();

    expect($xml)->toContain('<dc:identifier>https://doi.org/')
        ->toContain('<dc:identifier>'.route('articles.show', 'a-published-article').'</dc:identifier>');
});

it('NEVER exposes an unpublished article to a harvester', function () {
    // A draft leaking to an aggregator cannot be recalled — it has its own copy, and
    // takedowns take months.
    oaiArticle();
    $draft = Article::factory()->create([
        'status' => ArticleStatus::Draft,
        'title' => 'SECRET EMBARGOED MANUSCRIPT',
        'slug' => 'embargoed',
    ]);

    $xml = $this->get('/oai?verb=ListRecords&metadataPrefix=oai_dc')->getContent();

    expect($xml)->not->toContain('SECRET EMBARGOED MANUSCRIPT')
        ->not->toContain('embargoed');

    $this->get('/oai?verb=GetRecord&metadataPrefix=oai_dc&identifier=oai:localhost:embargoed')
        ->assertOk()   // OAI errors are 200 with an <error> element, not a 4xx
        ->assertSee('idDoesNotExist');
});

it('rejects an unsupported metadata format rather than emitting the wrong one', function () {
    oaiArticle();

    $this->get('/oai?verb=ListRecords&metadataPrefix=marcxml')
        ->assertOk()
        ->assertSee('cannotDisseminateFormat');
});

it('rejects an illegal verb', function () {
    $this->get('/oai?verb=Nonsense')->assertOk()->assertSee('badVerb');
});

it('returns well-formed XML', function () {
    oaiArticle();

    foreach (['Identify', 'ListMetadataFormats', 'ListSets'] as $verb) {
        $xml = $this->get("/oai?verb={$verb}")->getContent();

        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        expect($doc)->not->toBeFalse("verb={$verb} produced malformed XML");
    }
});

it('scopes a set to one journal', function () {
    oaiArticle();

    $other = Journal::factory()->create(['slug' => 'other-journal']);
    Article::factory()->published()->create([
        'journal_id' => $other->id,
        'slug' => 'other-article',
        'title' => 'From the other journal',
    ]);

    $xml = $this->get('/oai?verb=ListRecords&metadataPrefix=oai_dc&set=journal:jcdms')->getContent();

    expect($xml)->toContain('A published article')
        ->not->toContain('From the other journal');
});
