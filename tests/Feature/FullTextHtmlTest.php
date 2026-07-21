<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\ArticleAuthor;
use App\Models\Journal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Phase 3 — the crawlable HTML full text.
 *
 * Google Scholar reads the full body from citation_fulltext_html_url, running no JavaScript.
 * So the same acceptance bar as the citation meta applies: a plain GET must return the full
 * text in the raw HTML, and it must be XSS-safe because editors author the body.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function publishedArticleWithBody(?string $body): Article
{
    $journal = Journal::factory()->create([
        'slug' => 'jcdms',
        'abbreviation' => 'JCD&MS',
        'doi_prefix' => '10.12345',
        'issn_online' => '2755-0001',
    ]);

    $article = Article::factory()->published()->create([
        'journal_id' => $journal->id,
        'slug' => 'coral-refugia',
        'doi_suffix' => 'jcdms.v10i2.007',
        'title' => 'Coral refugia under repeated thermal stress',
        'abstract' => 'A study of thermal refugia across three reef systems and what they imply for resilience.',
        'body' => $body,
        'published_at' => '2026-03-01',
    ]);

    ArticleAuthor::factory()->create([
        'article_id' => $article->id,
        'given_name' => 'Ada', 'family_name' => 'King',
        'affiliation' => 'London Churchill College',
        'sequence' => 1, 'is_corresponding' => true,
    ]);

    return $article;
}

it('renders a crawlable full-text page with the body and the meta tags', function () {
    publishedArticleWithBody("## Methods\n\nWe surveyed three reef systems over five years.");

    $response = $this->get('/articles/coral-refugia.html')->assertOk();

    // The full text is in the RAW HTML — markdown rendered to headings and paragraphs.
    $response->assertSee('Coral refugia under repeated thermal stress');
    $response->assertSee('We surveyed three reef systems', false);
    $response->assertSee('<h2>Methods</h2>', false);

    // And the citation meta the harvesters read.
    $response->assertSee('name="citation_title"', false);
    $response->assertSee('name="citation_fulltext_html_url"', false);
});

it('advertises citation_fulltext_html_url on the landing page when there is a body', function () {
    publishedArticleWithBody('The full text of the article.');

    // The landing page's meta is Blade-rendered (survives a dead SSR process).
    $this->get('/articles/coral-refugia')
        ->assertOk()
        ->assertSee('name="citation_fulltext_html_url"', false)
        ->assertSee('/articles/coral-refugia.html', false);
});

it('escapes raw HTML in the body — an editor cannot inject script into a public page', function () {
    publishedArticleWithBody("Normal text.\n\n<script>alert('xss')</script>");

    $response = $this->get('/articles/coral-refugia.html')->assertOk();

    // The script tag must appear as escaped text, never as an executable element.
    $response->assertDontSee("<script>alert('xss')</script>", false);
    $response->assertSee('&lt;script&gt;', false);
});

it('404s the full-text page when the article has no body', function () {
    publishedArticleWithBody(null);

    $this->get('/articles/coral-refugia.html')->assertNotFound();

    // And it does not advertise a full-text URL that would 404.
    $this->get('/articles/coral-refugia')
        ->assertOk()
        ->assertDontSee('citation_fulltext_html_url', false);
});

it('404s a draft full-text page for a guest, but lets an editor preview it', function () {
    $article = publishedArticleWithBody('Draft body.');
    $article->update(['status' => ArticleStatus::Draft]);

    $this->get('/articles/coral-refugia.html')->assertNotFound();

    $editor = grantRoleOn(User::factory()->create(), $article->journal, 'journal-editor');
    $this->actingAs($editor)
        ->get('/articles/coral-refugia.html')
        ->assertOk()
        ->assertSee('name="robots"', false); // previews are noindex
});
