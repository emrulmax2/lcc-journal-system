<?php

declare(strict_types=1);

use App\Actions\PublishArticleAction;
use App\Actions\PublishIssueAction;
use App\Enums\ArticleFileType;
use App\Enums\ArticleStatus;
use App\Enums\IssueStatus;
use App\Exceptions\FrozenIdentifierException;
use App\Jobs\DepositToCrossref;
use App\Models\Article;
use App\Models\ArticleAuthor;
use App\Models\ArticleFile;
use App\Models\Issue;
use App\Models\Journal;
use App\Models\JournalSection;
use App\Models\Volume;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(fn () => Queue::fake());

function publishableArticle(array $overrides = []): Article
{
    $journal = Journal::factory()->create([
        'abbreviation' => 'JCDMS',
        'doi_prefix' => '10.12345',
        'doi_suffix_pattern' => '{journal}.v{volume}i{issue}.{seq}',
    ]);

    $section = JournalSection::factory()->create(['journal_id' => $journal->id]);
    $volume = Volume::factory()->create(['journal_id' => $journal->id, 'number' => 10]);
    $issue = Issue::factory()->create(['volume_id' => $volume->id, 'number' => 2]);

    $article = Article::factory()->create(array_merge([
        'journal_id' => $journal->id,
        'issue_id' => $issue->id,
        'journal_section_id' => $section->id,
        'sequence' => 1,
        'title' => 'A publishable article',
        'abstract' => 'An abstract that exists.',
        'first_page' => 1,
        'last_page' => 10,
        'status' => ArticleStatus::Draft,
        'doi_suffix' => null,
    ], $overrides));

    ArticleAuthor::factory()->create(['article_id' => $article->id, 'sequence' => 1]);
    ArticleFile::factory()->create(['article_id' => $article->id, 'type' => ArticleFileType::Pdf]);

    return $article->fresh();
}

describe('pre-flight', function () {
    it('reports EVERY problem at once, not just the first', function () {
        // An editor fixing a publication one error at a time, against a deadline, is how a
        // half-complete article goes live.
        $article = publishableArticle(['title' => '', 'abstract' => null, 'first_page' => null, 'last_page' => null]);
        $article->authors()->delete();
        $article->files()->delete();

        try {
            app(PublishArticleAction::class)->execute($article->fresh());
            $this->fail('Expected the publish to be refused.');
        } catch (ValidationException $e) {
            $keys = array_keys($e->errors());

            // All five failures, in one response.
            expect($keys)->toContain('title')
                ->toContain('abstract')
                ->toContain('pdf')
                ->toContain('authors')
                ->toContain('pages');
        }
    });

    it('refuses an article with no PDF, because citation_pdf_url would 404', function () {
        $article = publishableArticle();
        $article->files()->delete();

        expect(fn () => app(PublishArticleAction::class)->execute($article->fresh()))
            ->toThrow(ValidationException::class);
    });

    it('refuses an article with neither named authors nor a corporate author', function () {
        $article = publishableArticle();
        $article->authors()->delete();

        expect(fn () => app(PublishArticleAction::class)->execute($article->fresh()))
            ->toThrow(ValidationException::class);
    });

    it('ACCEPTS an article with a corporate author and zero named authors', function () {
        // The real JCD&MS editorial. This must not be mistaken for "no author".
        $article = publishableArticle(['corporate_author' => 'Members of the CLIR, London Churchill College']);
        $article->authors()->delete();

        $published = app(PublishArticleAction::class)->execute($article->fresh());

        expect($published->status)->toBe(ArticleStatus::Published);
    });

    it('refuses an article with BOTH named authors and a corporate author', function () {
        // Crossref accepts one or the other. Sending both deposits a contradiction.
        $article = publishableArticle(['corporate_author' => 'Some Centre']);

        expect(fn () => app(PublishArticleAction::class)->execute($article->fresh()))
            ->toThrow(ValidationException::class);
    });

    it('refuses two articles at the same position in an issue', function () {
        // They would derive the SAME DOI suffix. The unique index would catch it — as a
        // 500, after the first one was already live.
        $first = publishableArticle(['sequence' => 3]);

        $second = Article::factory()->create([
            'journal_id' => $first->journal_id,
            'issue_id' => $first->issue_id,
            'journal_section_id' => $first->journal_section_id,
            'sequence' => 3,
            'abstract' => 'x',
            'first_page' => 20, 'last_page' => 30,
        ]);
        ArticleAuthor::factory()->create(['article_id' => $second->id]);
        ArticleFile::factory()->create(['article_id' => $second->id, 'type' => ArticleFileType::Pdf]);

        expect(fn () => app(PublishArticleAction::class)->execute($second->fresh()))
            ->toThrow(ValidationException::class);
    });

    it('refuses overlapping page ranges within an issue', function () {
        $first = publishableArticle(['sequence' => 1, 'first_page' => 1, 'last_page' => 10]);

        $second = Article::factory()->create([
            'journal_id' => $first->journal_id,
            'issue_id' => $first->issue_id,
            'journal_section_id' => $first->journal_section_id,
            'sequence' => 2,
            'abstract' => 'x',
            'first_page' => 8, 'last_page' => 20,   // overlaps 8-10
        ]);
        ArticleAuthor::factory()->create(['article_id' => $second->id]);
        ArticleFile::factory()->create(['article_id' => $second->id, 'type' => ArticleFileType::Pdf]);

        try {
            app(PublishArticleAction::class)->execute($second->fresh());
            $this->fail('Expected overlapping pages to be refused.');
        } catch (ValidationException $e) {
            expect($e->errors()['pages'][0])->toContain('overlap');
        }
    });

    it('refuses a page range that ends before it starts', function () {
        $article = publishableArticle(['first_page' => 30, 'last_page' => 12]);

        expect(fn () => app(PublishArticleAction::class)->execute($article))
            ->toThrow(ValidationException::class);
    });
});

describe('publishing', function () {
    it('mints the DOI suffix, stamps published_at, and freezes the identifiers', function () {
        $article = publishableArticle(['sequence' => 5]);

        $published = app(PublishArticleAction::class)->execute($article);

        expect($published->status)->toBe(ArticleStatus::Published)
            ->and($published->doi_suffix)->toBe('jcdms.v10i2.005')
            ->and($published->doi())->toBe('10.12345/jcdms.v10i2.005')
            ->and($published->published_at)->not->toBeNull();

        // And it is now permanent.
        expect(fn () => $published->update(['slug' => 'something-else']))
            ->toThrow(FrozenIdentifierException::class);
    });

    it('dispatches the Crossref deposit — OUTSIDE the publish transaction', function () {
        $article = publishableArticle();

        app(PublishArticleAction::class)->execute($article);

        Queue::assertPushed(DepositToCrossref::class);
    });

    it('does NOT dispatch a deposit while the journal has no DOI prefix', function () {
        // Nothing to deposit against. Publishing must still work — the DOI is registered
        // later, once Crossref issues the prefix.
        $article = publishableArticle();
        $article->journal->update(['doi_prefix' => null]);

        $published = app(PublishArticleAction::class)->execute($article->fresh());

        expect($published->status)->toBe(ArticleStatus::Published);
        Queue::assertNothingPushed();
    });
});

describe('publishing an issue', function () {
    it('publishes every article together and deposits them as ONE Crossref batch', function () {
        $first = publishableArticle(['sequence' => 1, 'first_page' => 1, 'last_page' => 10]);
        $issue = $first->issue;

        $second = Article::factory()->create([
            'journal_id' => $first->journal_id,
            'issue_id' => $issue->id,
            'journal_section_id' => $first->journal_section_id,
            'sequence' => 2,
            'abstract' => 'x',
            'first_page' => 11, 'last_page' => 20,
        ]);
        ArticleAuthor::factory()->create(['article_id' => $second->id]);
        ArticleFile::factory()->create(['article_id' => $second->id, 'type' => ArticleFileType::Pdf]);

        $published = app(PublishIssueAction::class)->execute($issue->fresh());

        expect($published->status)->toBe(IssueStatus::Published)
            ->and($published->publication_date)->not->toBeNull();

        expect(Article::where('issue_id', $issue->id)->where('status', ArticleStatus::Published)->count())->toBe(2);
        expect($first->fresh()->doi_suffix)->toBe('jcdms.v10i2.001')
            ->and($second->fresh()->doi_suffix)->toBe('jcdms.v10i2.002');

        // One batch for the whole issue — one deposit record to retry, not N.
        Queue::assertPushed(DepositToCrossref::class, 1);
    });

    it('publishes NOTHING if any article in the issue fails pre-flight', function () {
        // A half-published issue — page numbers referring to articles nobody can read —
        // is worse than an unpublished one.
        $good = publishableArticle(['sequence' => 1, 'first_page' => 1, 'last_page' => 10]);
        $issue = $good->issue;

        $bad = Article::factory()->create([
            'journal_id' => $good->journal_id,
            'issue_id' => $issue->id,
            'sequence' => 2,
            'abstract' => null,     // no abstract
            'first_page' => null,   // no pages
        ]);

        expect(fn () => app(PublishIssueAction::class)->execute($issue->fresh()))
            ->toThrow(ValidationException::class);

        expect($good->fresh()->status)->toBe(ArticleStatus::Draft)
            ->and($bad->fresh()->status)->toBe(ArticleStatus::Draft)
            ->and($issue->fresh()->status)->toBe(IssueStatus::Draft);

        Queue::assertNothingPushed();
    });
});
