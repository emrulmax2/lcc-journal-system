<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Journal;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Phase 6 — Atom syndication and a fuller sitemap. Server-rendered XML, like the sitemap and
 * OAI: a feed reader runs no JavaScript, so the content must be in the raw response.
 */
function publishedArticleIn(Journal $journal, string $slug, string $title): Article
{
    return Article::factory()->published()->create([
        'journal_id' => $journal->id,
        'slug' => $slug,
        'title' => $title,
        'published_at' => '2026-03-01',
    ]);
}

it('serves a site-wide Atom feed of published articles', function () {
    $journal = Journal::factory()->create(['slug' => 'jcdms', 'is_active' => true]);
    publishedArticleIn($journal, 'coral-refugia', 'Coral refugia under thermal stress');
    Article::factory()->create(['journal_id' => $journal->id, 'slug' => 'a-draft', 'status' => ArticleStatus::Draft]);

    $response = $this->get('/feed')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/atom+xml');
    $response->assertSee('<feed', false);
    $response->assertSee('Coral refugia under thermal stress');
    // A draft must never appear in the feed.
    $response->assertDontSee('a-draft', false);
});

it('serves a per-journal Atom feed scoped to that journal', function () {
    $a = Journal::factory()->create(['slug' => 'jcdms', 'title' => 'JCDMS', 'is_active' => true]);
    $b = Journal::factory()->create(['slug' => 'other', 'title' => 'Other Journal', 'is_active' => true]);

    publishedArticleIn($a, 'in-jcdms', 'An article in JCDMS');
    publishedArticleIn($b, 'in-other', 'An article in the other journal');

    $response = $this->get('/journals/jcdms/feed')->assertOk();

    $response->assertSee('An article in JCDMS');
    $response->assertDontSee('An article in the other journal');
});

it('lists journal landing pages in the sitemap', function () {
    Journal::factory()->create(['slug' => 'jcdms', 'is_active' => true]);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('/journals/jcdms', false);
});

it('advertises the feed for discovery on a normal page', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('type="application/atom+xml"', false);
});
