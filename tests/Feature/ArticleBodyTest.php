<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\Journal;
use App\Services\Content\ArticleBody;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * The article page shows the full text as real HTML — headings, tables, figures — with an
 * "On this page" list built from its section headings. It used to print the Markdown as
 * plain text ("## Introduction").
 */
const SAMPLE_BODY = <<<'MD'
## Introduction

Some *opening* text.

## Literature Review

### From Anti-Colonialism to Post-Colonial Critique

| Group | Count |
|---|---|
| A | 12 |

![Fig. 1](/storage/articles/1/figures/fig-01-p3.png)

*Figure 1: Pathways of care*

## Introduction

Repeated heading.

## Abstract

A body heading that must not steal the page's own "abstract" anchor.
MD;

it('renders headings with ids, and lists h2/h3 as the contents', function () {
    $out = app(ArticleBody::class)->render(SAMPLE_BODY);

    expect($out['html'])->toContain('<h2 id="introduction">Introduction</h2>')
        ->toContain('<h3 id="from-anti-colonialism-to-post-colonial-critique">')
        ->not->toContain('## ')
        ->and(array_column($out['toc'], 'id'))->toBe([
            'introduction', 'literature-review', 'from-anti-colonialism-to-post-colonial-critique',
            'introduction-2',   // a repeated heading gets its own anchor
            'abstract-2',       // never the page's own #abstract
        ])
        ->and($out['toc'][2]['level'])->toBe(3);
});

it('wraps tables, marks figure captions and lazy-loads images', function () {
    $html = app(ArticleBody::class)->render(SAMPLE_BODY)['html'];

    expect($html)->toContain('<div class="table-wrap"><table>')
        ->toContain('<p class="caption"><em>Figure 1: Pathways of care</em></p>')
        ->toContain('<img loading="lazy" decoding="async" src="/storage/articles/1/figures/fig-01-p3.png"')
        // A paragraph that merely CONTAINS italics is not a caption.
        ->toContain('<p>Some <em>opening</em> text.</p>');
});

it('never lets raw HTML through', function () {
    $html = app(ArticleBody::class)->render("## Hi\n\n<script>alert(1)</script>\n\n<img src=x onerror=alert(1)>")['html'];

    expect($html)->not->toContain('<script>')
        ->not->toContain('<img src=x')
        ->toContain('&lt;script&gt;');
});

it('sends the article page rendered HTML and its contents, not raw Markdown', function () {
    $journal = Journal::factory()->create(['slug' => 'jcdms']);
    Article::factory()->published()->create([
        'journal_id' => $journal->id, 'slug' => 'rendered', 'body' => SAMPLE_BODY,
    ]);

    $this->get('/articles/rendered')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ArticleDetail')
            ->missing('article.body')
            ->where('article.bodyHtml', fn (string $html) => str_contains($html, '<h2 id="literature-review">'))
            ->where('article.toc.1', ['id' => 'literature-review', 'text' => 'Literature Review', 'level' => 2])
        );
});

it('gives the crawlable full-text page the same section anchors', function () {
    $journal = Journal::factory()->create(['slug' => 'jcdms']);
    Article::factory()->published()->create(['journal_id' => $journal->id, 'slug' => 'anchored', 'body' => SAMPLE_BODY]);

    $this->get('/articles/anchored.html')->assertOk()->assertSee('<h2 id="introduction">Introduction</h2>', false);
});
