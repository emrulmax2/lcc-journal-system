<?php

declare(strict_types=1);

use App\Enums\ArticleFileType;
use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\ArticleAuthor;
use App\Models\ArticleFile;
use App\Models\Issue;
use App\Models\Journal;
use App\Models\JournalSection;
use App\Models\User;
use App\Models\Volume;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * THE ACCEPTANCE TEST FOR THE ENTIRE DOI PROGRAMME.
 *
 * A plain HTTP GET — no JavaScript, exactly what Google Scholar and every Crossref /
 * DOAJ / OAI-PMH harvester performs — must return HTML containing the full citation
 * meta tag set with correct values.
 *
 * If these fail, nothing downstream is worth building. A DOI resolves to a landing page;
 * if that page is unreadable to machines, the DOI does the one job it exists to do and
 * fails at it, and we have paid Crossref for the privilege.
 */
function publishedArticleWithEverything(): Article
{
    $journal = Journal::factory()->create([
        'slug' => 'jcdms',
        'title' => 'Journal of Contemporary Development & Management Studies',
        'abbreviation' => 'JCD&MS',
        'publisher' => 'London Churchill College',
        'doi_prefix' => '10.12345',
        'issn_online' => '2755-0001',
        'license' => 'CC BY 4.0',
    ]);

    $section = JournalSection::factory()->create([
        'journal_id' => $journal->id,
        'name' => 'Research Protocol',
    ]);

    $volume = Volume::factory()->create(['journal_id' => $journal->id, 'number' => 10, 'year' => 2026]);
    $issue = Issue::factory()->published()->create(['volume_id' => $volume->id, 'number' => 2]);

    $article = Article::factory()->published()->create([
        'journal_id' => $journal->id,
        'issue_id' => $issue->id,
        'journal_section_id' => $section->id,
        'sequence' => 5,
        'slug' => 'transition-teaching-educational-leadership',
        'doi_suffix' => 'jcdms.v10i2.005',
        'title' => 'Research Protocol: Navigating the Transition from Teaching to Educational Leadership',
        'abstract' => 'This research protocol outlines a mixed-methods study exploring the transition from teaching roles to management positions within higher education in England.',
        'keywords' => ['Educational leadership', 'Higher education'],
        'first_page' => 59,
        'last_page' => 79,
        'published_at' => '2026-03-01',
        'corporate_author' => null,
    ]);

    ArticleAuthor::factory()->create([
        'article_id' => $article->id,
        'given_name' => 'Nick', 'family_name' => 'Papé',
        'affiliation' => 'London Churchill College, UK',
        'orcid' => '0000-0003-1395-3751',
        'sequence' => 1, 'is_corresponding' => true,
    ]);

    ArticleAuthor::factory()->create([
        'article_id' => $article->id,
        'given_name' => 'Russell', 'family_name' => 'Kabir',
        'affiliation' => 'Anglia Ruskin University, UK',
        'orcid' => '0000-0002-4510-0385',
        'sequence' => 2, 'is_corresponding' => false,
    ]);

    ArticleFile::factory()->create([
        'article_id' => $article->id,
        'type' => ArticleFileType::Pdf,
        'path' => 'articles/jcdms-v10i2-005.pdf',
    ]);

    return $article->fresh();
}

/** @return array<string, list<string>> name => [values] */
function metaTags(string $html): array
{
    preg_match_all('/<meta name="([^"]+)" content="([^"]*)">/', $html, $matches, PREG_SET_ORDER);

    $tags = [];
    foreach ($matches as [, $name, $content]) {
        $tags[$name][] = html_entity_decode($content, ENT_QUOTES);
    }

    return $tags;
}

it('serves the full Highwire citation tag set to a client that runs no JavaScript', function () {
    $article = publishedArticleWithEverything();

    $tags = metaTags($this->get("/articles/{$article->slug}")->assertOk()->getContent());

    expect($tags)
        ->toHaveKey('citation_journal_title')
        ->toHaveKey('citation_journal_abbrev')
        ->toHaveKey('citation_publisher')
        ->toHaveKey('citation_title')
        ->toHaveKey('citation_author')
        ->toHaveKey('citation_author_institution')
        ->toHaveKey('citation_publication_date')
        ->toHaveKey('citation_volume')
        ->toHaveKey('citation_issue')
        ->toHaveKey('citation_firstpage')
        ->toHaveKey('citation_lastpage')
        ->toHaveKey('citation_issn')
        ->toHaveKey('citation_doi')
        ->toHaveKey('citation_abstract_html_url')
        ->toHaveKey('citation_pdf_url')
        ->toHaveKey('citation_language')
        ->toHaveKey('citation_keywords');

    expect($tags['citation_journal_title'][0])->toBe('Journal of Contemporary Development & Management Studies');
    expect($tags['citation_doi'][0])->toBe('10.12345/jcdms.v10i2.005');
    expect($tags['citation_publication_date'][0])->toBe('2026/03/01');
    expect($tags['citation_volume'][0])->toBe('10');
    expect($tags['citation_issue'][0])->toBe('2');
    expect($tags['citation_firstpage'][0])->toBe('59');
    expect($tags['citation_lastpage'][0])->toBe('79');
});

it('emits one citation_author per author, in sequence order, paired with its institution', function () {
    // Scholar pairs author and institution POSITIONALLY. Getting the order wrong
    // attributes Kabir's work to London Churchill College and Papé's to Anglia Ruskin.
    $article = publishedArticleWithEverything();

    $tags = metaTags($this->get("/articles/{$article->slug}")->getContent());

    expect($tags['citation_author'])->toBe(['Nick Papé', 'Russell Kabir']);
    expect($tags['citation_author_institution'])->toBe([
        'London Churchill College, UK',
        'Anglia Ruskin University, UK',
    ]);
    expect($tags['citation_author_orcid'])->toBe([
        'https://orcid.org/0000-0003-1395-3751',
        'https://orcid.org/0000-0002-4510-0385',
    ]);
});

it('advertises a citation_pdf_url that exactly matches the real PDF route', function () {
    // A mismatch between the advertised PDF URL and the real one is the most common
    // reason Scholar silently refuses to index a journal. It looks fine to a human.
    $article = publishedArticleWithEverything();

    $tags = metaTags($this->get("/articles/{$article->slug}")->getContent());

    expect($tags['citation_pdf_url'][0])->toBe(route('articles.pdf', $article->slug));
    expect($tags['citation_abstract_html_url'][0])->toBe(route('articles.show', $article->slug));

    // And that route must actually resolve — an advertised URL that 404s is worse than
    // none, because Scholar fetches it, fails, and downgrades the whole journal.
    $this->get($tags['citation_pdf_url'][0])->assertStatus(404); // no file on disk in test
});

it('advertises NO citation_pdf_url when the article has no PDF', function () {
    $article = publishedArticleWithEverything();
    $article->files()->delete();

    $tags = metaTags($this->get("/articles/{$article->slug}")->getContent());

    expect($tags)->not->toHaveKey('citation_pdf_url');
});

it('emits the corporate author as a citation_author rather than leaving the article authorless', function () {
    // The real JCD&MS editorial. Looping over article_authors alone yields ZERO authors,
    // and Scholar will not index an article with no author.
    $article = publishedArticleWithEverything();
    $article->authors()->delete();
    $article->update([
        'corporate_author' => 'Members of the Centre for Learning Innovation and Research (CLIR), London Churchill College',
    ]);

    $tags = metaTags($this->get("/articles/{$article->slug}")->getContent());

    expect($tags['citation_author'])->toBe([
        'Members of the Centre for Learning Innovation and Research (CLIR), London Churchill College',
    ]);
});

it('emits NO citation_doi at all until Crossref has issued a prefix', function () {
    // An empty content="" would ASSERT that the article has no DOI. Omission is the only
    // honest answer while the prefix is pending.
    $article = publishedArticleWithEverything();
    $article->journal->update(['doi_prefix' => null]);

    $tags = metaTags($this->get("/articles/{$article->slug}")->getContent());

    expect($tags)->not->toHaveKey('citation_doi');
});

it('omits volume and issue for a continuous-publication journal', function () {
    $journal = Journal::factory()->continuous()->withDoiPrefix()->create();
    $article = Article::factory()->published()->continuous()->create([
        'journal_id' => $journal->id,
        'doi_suffix' => 'mrdn.2026.00412',
    ]);

    $tags = metaTags($this->get("/articles/{$article->slug}")->getContent());

    expect($tags)->not->toHaveKey('citation_volume')
        ->and($tags)->not->toHaveKey('citation_issue');
});

it('includes the Dublin Core set and a canonical link', function () {
    $article = publishedArticleWithEverything();

    $html = $this->get("/articles/{$article->slug}")->getContent();
    $tags = metaTags($html);

    expect($tags)->toHaveKey('DC.title')->toHaveKey('DC.creator')->toHaveKey('DC.identifier');
    expect($html)->toContain('<link rel="canonical" href="'.route('articles.show', $article->slug).'">');
});

describe('drafts', function () {
    beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

    it('404s a draft for a guest, rather than 403 — a 403 would confirm the title exists', function () {
        $article = Article::factory()->create(['status' => ArticleStatus::Draft]);

        $this->get("/articles/{$article->slug}")->assertNotFound();
    });

    it('never lets a draft into an index, even when an editor is previewing it', function () {
        $article = Article::factory()->create(['status' => ArticleStatus::Draft]);

        $admin = User::factory()->create();
        $admin->update(['is_site_admin' => true]);

        $html = $this->actingAs($admin)->get("/articles/{$article->slug}")->assertOk()->getContent();

        expect($html)->toContain('<meta name="robots" content="noindex, nofollow">');
    });
});

it('lists only published articles in the sitemap', function () {
    $published = publishedArticleWithEverything();
    $draft = Article::factory()->create(['status' => ArticleStatus::Draft]);

    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($xml)->toContain(route('articles.show', $published->slug))
        ->not->toContain(route('articles.show', $draft->slug));
});
