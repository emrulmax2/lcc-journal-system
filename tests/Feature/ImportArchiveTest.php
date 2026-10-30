<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Enums\IssueStatus;
use App\Models\Article;
use App\Models\Issue;
use App\Models\Journal;
use App\Models\Volume;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * journal:import-archive loads the converted print back-catalogue. It must only ever create
 * DRAFTS (publishing freezes identifiers, which is an editor's decision), must be safe to
 * re-run, and must never touch a published article.
 */
beforeEach(function () {
    Storage::fake('public');
    Storage::fake('private');

    $this->journal = Journal::factory()->create(['slug' => 'jcdms', 'abbreviation' => 'JCD&MS', 'doi_prefix' => null]);
    $this->root = storage_path('framework/testing/archive-'.uniqid());

    $issue = $this->root.'/vol05-no1-2017';
    File::ensureDirectoryExists($issue.'/articles/01-contents');
    File::ensureDirectoryExists($issue.'/articles/02-big-data/figures');

    File::put($issue.'/cover.jpg', 'jpeg-bytes');
    File::put($issue.'/vol05-no1-2017-full-issue.pdf', '%PDF-1.4 whole issue');
    File::put($issue.'/issue.json', json_encode([
        'volume' => 5, 'number' => 1, 'season' => 'Summer 2017', 'year' => 2017, 'cover' => 'cover.jpg',
        'articles' => [
            ['sequence' => 1, 'folder' => 'articles/01-contents', 'type' => 'front-matter'],
            ['sequence' => 2, 'folder' => 'articles/02-big-data', 'type' => 'research-article'],
        ],
    ]));

    $article = $issue.'/articles/02-big-data';
    File::put($article.'/body.md', "## Method\n\nWe looked.\n\n![Fig. 1](figures/fig-01-p9.png)\n");
    File::put($article.'/figures/fig-01-p9.png', 'png-bytes');
    File::put($article.'/article.pdf', '%PDF-1.4 test');
    File::put($article.'/thumbnail.jpg', 'jpeg-first-page');
    File::put($article.'/article.json', json_encode([
        'volume' => 5, 'number' => 1, 'sequence' => 2, 'type' => 'research-article',
        'section' => 'Case Study', 'title' => 'Big data in Sørensen’s café', 'slug' => 'big-data',
        'corporate_author' => null, 'abstract' => 'An abstract.', 'keywords' => ['data'],
        'first_page' => 9, 'last_page' => 14,
        'authors' => [['given_name' => 'Ana', 'family_name' => 'Ramírez', 'affiliation' => 'LCC',
            'email' => null, 'orcid' => null, 'is_corresponding' => true]],
        'references' => [['raw_text' => 'Smith, J. (2010) A book.', 'doi' => null]],
        'notes' => ['1\\. A footnote.'],
    ]));
});

afterEach(fn () => File::deleteDirectory($this->root));

it('imports issues and articles as drafts, with figures, notes, references and the PDF', function () {
    $this->artisan('journal:import-archive', ['path' => $this->root])->assertSuccessful();

    $issue = Issue::sole();
    expect($issue->status)->toBe(IssueStatus::Draft)
        ->and($issue->season)->toBe('Summer 2017')
        ->and($issue->cover_path)->not->toBeNull();
    Storage::disk('public')->assertExists($issue->cover_path);
    Storage::disk('private')->assertExists($issue->full_pdf_path);

    // Front matter is skipped; the first real article takes position 1.
    $article = Article::sole();
    expect($article->status)->toBe(ArticleStatus::Draft)
        ->and($article->sequence)->toBe(1)
        ->and($article->doi_suffix)->toBeNull()
        ->and($article->title)->toBe('Big data in Sørensen’s café')
        ->and($article->section->name)->toBe('Case Study')
        ->and($article->authors->sole()->family_name)->toBe('Ramírez')
        ->and($article->references()->count())->toBe(1)
        ->and($article->body)->toContain("](/storage/articles/{$article->id}/figures/fig-01-p9.png)")
        ->and($article->body)->toContain("## Notes\n\n1\\. A footnote.")
        ->and($article->body)->not->toContain('](figures/');

    Storage::disk('public')->assertExists("articles/{$article->id}/figures/fig-01-p9.png");
    Storage::disk('private')->assertExists($article->pdf->path);
    Storage::disk('public')->assertExists($article->thumbnail_path);
});

it('is safe to run twice', function () {
    $this->artisan('journal:import-archive', ['path' => $this->root])->assertSuccessful();
    $this->artisan('journal:import-archive', ['path' => $this->root])->assertSuccessful();

    expect(Issue::count())->toBe(1)
        ->and(Article::count())->toBe(1)
        ->and(Article::sole()->references()->count())->toBe(1)
        ->and(Article::sole()->files()->count())->toBe(1);
});

it('never touches a published article', function () {
    $volume = Volume::factory()->create(['journal_id' => $this->journal->id, 'number' => 5, 'year' => 2017]);
    $issue = Issue::factory()->create(['volume_id' => $volume->id, 'number' => 1]);
    $published = Article::factory()->published()->create([
        'journal_id' => $this->journal->id, 'issue_id' => $issue->id, 'sequence' => 1,
        'first_page' => 9, 'last_page' => 14, 'slug' => 'already-live', 'title' => 'Live title', 'body' => 'Live body',
    ]);

    $this->artisan('journal:import-archive', ['path' => $this->root])->assertSuccessful();

    $published->refresh();
    expect(Article::count())->toBe(1)
        ->and($published->title)->toBe('Live title')
        ->and($published->body)->toBe('Live body')
        // The card thumbnail is presentation, not the record — a published article still gets one.
        ->and($published->thumbnail_path)->toBe("articles/{$published->id}/thumbnail.jpg");
});

it('keeps an existing draft’s slug and authors, and only fills what is blank', function () {
    $volume = Volume::factory()->create(['journal_id' => $this->journal->id, 'number' => 5, 'year' => 2017]);
    $issue = Issue::factory()->create(['volume_id' => $volume->id, 'number' => 1]);
    $draft = Article::factory()->create([
        'journal_id' => $this->journal->id, 'issue_id' => $issue->id, 'status' => ArticleStatus::Draft,
        'sequence' => 1, 'first_page' => 9, 'last_page' => 14, 'slug' => 'seeded-slug',
        'title' => 'Seeded title', 'abstract' => null, 'body' => null,
    ]);

    $this->artisan('journal:import-archive', ['path' => $this->root])->assertSuccessful();

    $draft->refresh();
    expect($draft->slug)->toBe('seeded-slug')
        ->and($draft->title)->toBe('Seeded title')
        ->and($draft->abstract)->toBe('An abstract.')
        ->and($draft->body)->toContain('## Method');
});

function setIssueDate(string $root, ?string $date): void
{
    $file = $root.'/vol05-no1-2017/issue.json';
    $meta = json_decode(File::get($file), true);
    $meta['publication_date'] = $date;
    File::put($file, json_encode($meta));
}

it('publishes the back-catalogue on its original date, never today', function () {
    setIssueDate($this->root, '2017-08-24');
    $this->artisan('journal:import-archive', ['path' => $this->root])->assertSuccessful();

    $this->artisan('journal:publish-archive', ['path' => $this->root])->assertSuccessful();

    $article = Article::sole();
    $issue = Issue::sole();
    expect($article->status)->toBe(ArticleStatus::Published)
        ->and($article->published_at->toDateString())->toBe('2017-08-24')
        ->and($article->doi_suffix)->not->toBeNull()
        ->and($issue->status)->toBe(IssueStatus::Published)
        ->and($issue->publication_date->toDateString())->toBe('2017-08-24');

    // The landing page is live.
    $this->get('/articles/big-data')->assertOk();
});

it('refuses to publish an issue with no recorded date', function () {
    setIssueDate($this->root, null);
    $this->artisan('journal:import-archive', ['path' => $this->root])->assertSuccessful();

    $this->artisan('journal:publish-archive', ['path' => $this->root])->assertFailed();

    expect(Article::sole()->status)->toBe(ArticleStatus::Draft);
});

it('publishes an article that was printed without an abstract, but not one without a PDF', function () {
    setIssueDate($this->root, '2017-08-24');
    $this->artisan('journal:import-archive', ['path' => $this->root])->assertSuccessful();
    Article::sole()->update(['abstract' => null]);
    Article::sole()->files()->delete();

    $this->artisan('journal:publish-archive', ['path' => $this->root])->assertFailed();
    expect(Article::sole()->status)->toBe(ArticleStatus::Draft);

    $this->artisan('journal:import-archive', ['path' => $this->root])->assertSuccessful();   // restores the PDF
    $this->artisan('journal:publish-archive', ['path' => $this->root])->assertSuccessful();
    expect(Article::sole()->status)->toBe(ArticleStatus::Published);
});

it('writes nothing on a dry run', function () {
    $this->artisan('journal:import-archive', ['path' => $this->root, '--dry-run' => true])->assertSuccessful();

    expect(Issue::count())->toBe(0)->and(Article::count())->toBe(0);
});
