<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Enums\PublicationModel;
use App\Models\Article;
use App\Models\ArticleFile;
use App\Models\Issue;
use App\Models\Journal;
use App\Models\Volume;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * The journal is browsed ISSUE BY ISSUE: one card per published issue ("Volume 5 · Number 1
 * · Summer 2017"), each leading to that issue's table of contents and full-issue PDF.
 */
beforeEach(function () {
    Storage::fake('private');

    $this->journal = Journal::factory()->create([
        'slug' => 'jcdms',
        'title' => 'Journal of Contemporary Development & Management Studies',
        'abbreviation' => 'JCD&MS',
        'publication_model' => PublicationModel::IssueBased,
        'is_active' => true,
    ]);

    $v5 = Volume::factory()->create(['journal_id' => $this->journal->id, 'number' => 5, 'year' => 2017]);
    $v10 = Volume::factory()->create(['journal_id' => $this->journal->id, 'number' => 10, 'year' => 2026]);

    $this->old = Issue::factory()->published()->create([
        'volume_id' => $v5->id, 'number' => 1, 'season' => 'Summer 2017', 'publication_date' => '2017-08-24',
    ]);
    $this->new = Issue::factory()->published()->create([
        'volume_id' => $v10->id, 'number' => 2, 'season' => 'Spring 2026', 'publication_date' => '2026-09-09',
    ]);
    $this->draft = Issue::factory()->create(['volume_id' => $v10->id, 'number' => 3, 'season' => 'Autumn 2026']);

    // Two published articles in Vol 5 No 1, entered out of order, plus one draft.
    $this->second = Article::factory()->published()->create([
        'journal_id' => $this->journal->id, 'issue_id' => $this->old->id, 'sequence' => 2,
        'slug' => 'second-article', 'title' => 'Second in print', 'first_page' => 9, 'last_page' => 14,
    ]);
    $this->first = Article::factory()->published()->create([
        'journal_id' => $this->journal->id, 'issue_id' => $this->old->id, 'sequence' => 1,
        'slug' => 'first-article', 'title' => 'First in print', 'first_page' => 1, 'last_page' => 8,
    ]);
    Article::factory()->create([
        'journal_id' => $this->journal->id, 'issue_id' => $this->old->id, 'sequence' => 3,
        'status' => ArticleStatus::Draft, 'slug' => 'unpublished', 'title' => 'Not yet public',
    ]);
});

it('lists one card per PUBLISHED issue, newest first, titled with the journal', function () {
    $this->get('/journals')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Journals')
            ->has('issues', 2)
            ->where('issues.0.volume', 10)
            ->where('issues.0.number', 2)
            ->where('issues.0.season', 'Spring 2026')
            ->where('issues.0.journal.title', 'Journal of Contemporary Development & Management Studies')
            ->where('issues.0.url', route('journals.issues.show', ['journal' => 'jcdms', 'volume' => 10, 'number' => 2]))
            ->where('issues.1.volume', 5)
            // The card's article count is THIS issue's, and drafts are not counted.
            ->where('issues.1.articles', 2)
            ->where('volumes', [['number' => 10, 'years' => '2026'], ['number' => 5, 'years' => '2017']])
        );
});

it('filters by volume and sorts oldest first', function () {
    $this->get('/journals?volume=5')
        ->assertInertia(fn (Assert $page) => $page->has('issues', 1)->where('issues.0.volume', 5));

    $this->get('/journals?sort=oldest')
        ->assertInertia(fn (Assert $page) => $page->where('issues.0.volume', 5)->where('issues.1.volume', 10));
});

it('shows an issue’s articles in print order, without drafts', function () {
    $this->get('/journals/jcdms/volume/5/issue/1')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Issue')
            ->where('issue.label', 'Volume 5, Number 1')
            ->where('issue.season', 'Summer 2017')
            ->where('issue.publicationDate', '2017-08-24')
            ->has('articles', 2)
            ->where('articles.0.slug', 'first-article')
            ->where('articles.1.slug', 'second-article')
            ->where('next.url', route('journals.issues.show', ['journal' => 'jcdms', 'volume' => 10, 'number' => 2]))
            ->where('previous', null)
        );
});

it('404s for an unpublished, missing or misaddressed issue', function () {
    $this->get('/journals/jcdms/volume/10/issue/3')->assertNotFound();   // draft
    $this->get('/journals/jcdms/volume/5/issue/9')->assertNotFound();    // no such issue
    $this->get('/journals/jcdms/volume/10/issue/1')->assertNotFound();   // issue 1 is in no volume 10
    $this->get('/journals/other/volume/5/issue/1')->assertNotFound();    // no such journal
});

it('streams the full-issue PDF inline, and 404s when there is none', function () {
    $this->get('/journals/jcdms/volume/5/issue/1/pdf')->assertNotFound();

    Storage::disk('private')->put('issues/v5n1.pdf', '%PDF-1.4 whole issue');
    $this->old->update(['full_pdf_path' => 'issues/v5n1.pdf']);

    $response = $this->get('/journals/jcdms/volume/5/issue/1/pdf')->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toBe('inline; filename="JCDMS-Vol5-No1-Summer-2017.pdf"');

    $this->get('/journals/jcdms/volume/5/issue/1')
        ->assertInertia(fn (Assert $page) => $page->where('issue.fullPdfUrl', route('journals.issues.pdf', ['journal' => 'jcdms', 'volume' => 5, 'number' => 1])));
});

it('links each article’s PDF only when it has one', function () {
    ArticleFile::factory()->create(['article_id' => $this->first->id, 'type' => 'pdf']);

    $this->get('/journals/jcdms/volume/5/issue/1')
        ->assertInertia(fn (Assert $page) => $page
            ->where('articles.0.pdfUrl', $this->first->pdfUrl())
            ->where('articles.1.pdfUrl', null)
        );
});

it('puts the issues on the journal page, the homepage and the sitemap', function () {
    $this->get('/journals/jcdms')
        ->assertInertia(fn (Assert $page) => $page->component('Journal')->has('issues', 2));

    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page->has('featuredIssues', 2)->where('featuredIssues.0.volume', 10));

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee(route('journals.issues.show', ['journal' => 'jcdms', 'volume' => 5, 'number' => 1]), false)
        ->assertDontSee('/volume/10/issue/3', false);
});

it('links an article to its issue once the issue is public', function () {
    $this->get('/articles/first-article')
        ->assertInertia(fn (Assert $page) => $page->where(
            'article.issueUrl',
            route('journals.issues.show', ['journal' => 'jcdms', 'volume' => 5, 'number' => 1])
        ));
});

it('counts open-access ISSUES on the homepage — published ones only', function () {
    $this->journal->update(['open_access' => true]);

    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.2.label', 'Open-access issues')
            ->where('stats.2.value', 2)   // the draft issue is not counted
        );
});
