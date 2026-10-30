<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\Issue;
use App\Models\Journal;
use App\Models\JournalSection;
use App\Models\Media;
use App\Models\Volume;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * /articles — the archive search. Volume, issue ("Volume 10, Number 2 · Spring 2026"), type
 * and sort are all applied by the server, and every option carries a count computed with
 * the OTHER filters applied.
 */
beforeEach(function () {
    $journal = Journal::factory()->create(['slug' => 'jcdms', 'is_active' => true]);
    $research = JournalSection::factory()->create(['journal_id' => $journal->id, 'name' => 'Research Article']);
    $editorial = JournalSection::factory()->create(['journal_id' => $journal->id, 'name' => 'Editorial']);

    $v5 = Volume::factory()->create(['journal_id' => $journal->id, 'number' => 5, 'year' => 2017]);
    $v10 = Volume::factory()->create(['journal_id' => $journal->id, 'number' => 10, 'year' => 2025]);
    $v5n1 = Issue::factory()->published()->create(['volume_id' => $v5->id, 'number' => 1, 'season' => 'Summer 2017', 'publication_date' => '2017-08-24']);
    $v10n1 = Issue::factory()->published()->create(['volume_id' => $v10->id, 'number' => 1, 'season' => 'Autumn 2025', 'publication_date' => '2025-09-30']);
    $v10n2 = Issue::factory()->published()->create(['volume_id' => $v10->id, 'number' => 2, 'season' => 'Spring 2026', 'publication_date' => '2026-09-09']);

    $make = fn (Issue $issue, JournalSection $section, int $seq, string $title) => Article::factory()->published()->create([
        'journal_id' => $journal->id, 'issue_id' => $issue->id, 'journal_section_id' => $section->id,
        'sequence' => $seq, 'title' => $title, 'slug' => str($title)->slug(), 'published_at' => $issue->publication_date,
    ]);

    $make($v5n1, $editorial, 1, 'Alpha editorial 2017');
    $make($v5n1, $research, 2, 'Beta research 2017');
    $make($v10n1, $research, 1, 'Gamma research 2025');
    $make($v10n2, $editorial, 1, 'Delta editorial 2026');
    $make($v10n2, $research, 2, 'Epsilon research 2026');
});

it('offers every published issue in the dropdown, labelled as printed, with counts', function () {
    $this->get('/articles')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Articles')
            ->has('articles.data', 5)
            ->where('issues.0.label', 'Volume 10, Number 2 · Spring 2026')
            ->where('issues.0.count', 2)
            ->where('issues.2.label', 'Volume 5, Number 1 · Summer 2017')
            ->where('volumes', [
                ['number' => 10, 'years' => '2025–2026', 'count' => 3],
                ['number' => 5, 'years' => '2017', 'count' => 2],
            ])
            ->where('types', [['name' => 'Editorial', 'count' => 2], ['name' => 'Research Article', 'count' => 3]])
        );
});

it('filters by volume, then by issue, in print order', function () {
    $this->get('/articles?volume=10')
        ->assertInertia(fn (Assert $page) => $page
            ->has('articles.data', 3)
            ->where('filters.volume', 10)
            ->where('filters.issue', null)
        );

    $this->get('/articles?volume=10&issue=2')
        ->assertInertia(fn (Assert $page) => $page
            ->has('articles.data', 2)
            ->where('articles.data.0.title', 'Delta editorial 2026')
            ->where('articles.data.1.title', 'Epsilon research 2026')
            ->where('articles.data.0.printIssue', ['volume' => 10, 'number' => 2, 'season' => 'Spring 2026'])
        );
});

it('combines filters, and counts each facet with the OTHER filters applied', function () {
    $this->get('/articles?volume=10&type=Research+Article')
        ->assertInertia(fn (Assert $page) => $page
            ->has('articles.data', 2)
            // Types ignore the type filter but respect the volume: 1 editorial, 2 research.
            ->where('types', [['name' => 'Editorial', 'count' => 1], ['name' => 'Research Article', 'count' => 2]])
            // Issues ignore the volume filter but respect the type: research articles per issue.
            ->where('issues.0.count', 1)   // Vol 10 No 2
            ->where('issues.1.count', 1)   // Vol 10 No 1
            ->where('issues.2.count', 1)   // Vol 5 No 1
        );
});

it('sorts newest, oldest, or by title', function () {
    $this->get('/articles?sort=oldest')
        ->assertInertia(fn (Assert $page) => $page->where('articles.data.0.title', 'Alpha editorial 2017'));

    $this->get('/articles')
        ->assertInertia(fn (Assert $page) => $page->where('articles.data.0.title', 'Delta editorial 2026'));

    $this->get('/articles?sort=title')
        ->assertInertia(fn (Assert $page) => $page
            ->where('articles.data.0.title', 'Alpha editorial 2017')
            ->where('articles.data.4.title', 'Gamma research 2025'));
});

it('ignores an issue without a volume, and finds nothing in a volume that does not exist', function () {
    $this->get('/articles?issue=2')
        ->assertInertia(fn (Assert $page) => $page->has('articles.data', 5)->where('filters.issue', null));

    $this->get('/articles?volume=99')
        ->assertInertia(fn (Assert $page) => $page->has('articles.data', 0));
});

it('combines free text with the issue filter', function () {
    $this->get('/articles?volume=10&issue=2&q=Epsilon')
        ->assertInertia(fn (Assert $page) => $page->has('articles.data', 1)->where('articles.data.0.title', 'Epsilon research 2026'));
});

it('gives each card its first page, else its issue cover, never a stock photo', function () {
    Article::where('title', 'Delta editorial 2026')->update(['thumbnail_path' => 'articles/1/thumbnail.jpg']);
    $cover = Media::factory()->create(['path' => 'issues/v10n2-cover.jpg']);
    Issue::where('number', 2)->update(['cover_media_id' => $cover->id]);

    $this->get('/articles?volume=10&issue=2')
        ->assertInertia(fn (Assert $page) => $page
            ->where('articles.data.0.thumbnail', ['url' => '/storage/articles/1/thumbnail.jpg', 'kind' => 'page'])
            ->where('articles.data.1.thumbnail', ['url' => '/storage/issues/v10n2-cover.jpg', 'kind' => 'cover'])
        );

    $this->get('/articles?volume=5')
        ->assertInertia(fn (Assert $page) => $page->where('articles.data.0.thumbnail', null));
});
