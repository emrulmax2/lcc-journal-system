<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\Journal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * Citation counts come from citation indexes, which track articles by DOI. Until an article
 * has one, the count is unknown — sent as null and shown as "—", never as a claimed "0".
 */
it('sends no citation count for an article without a DOI', function () {
    $journal = Journal::factory()->create(['slug' => 'jcdms', 'doi_prefix' => null]);
    Article::factory()->published()->create([
        'journal_id' => $journal->id, 'slug' => 'no-doi', 'doi_suffix' => 'jcdms.v10i2.004', 'citations_count' => 0,
    ]);

    $this->get('/articles/no-doi')
        ->assertInertia(fn (Assert $page) => $page->where('article.citations', null)->where('article.doi', null));

    $this->get('/articles')
        ->assertInertia(fn (Assert $page) => $page->where('articles.data.0.citations', null));
});

it('sends the count once the article has a DOI', function () {
    $journal = Journal::factory()->create(['slug' => 'jcdms', 'doi_prefix' => '10.12345']);
    Article::factory()->published()->create([
        'journal_id' => $journal->id, 'slug' => 'with-doi', 'doi_suffix' => 'jcdms.v10i2.004', 'citations_count' => 7,
    ]);

    $this->get('/articles/with-doi')
        ->assertInertia(fn (Assert $page) => $page->where('article.citations', 7));
});
