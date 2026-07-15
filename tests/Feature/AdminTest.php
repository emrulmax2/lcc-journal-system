<?php

declare(strict_types=1);

use App\Enums\ArticleFileType;
use App\Enums\ArticleStatus;
use App\Enums\DepositStatus;
use App\Jobs\DepositToCrossref;
use App\Models\Article;
use App\Models\ArticleAuthor;
use App\Models\ArticleFile;
use App\Models\DoiDeposit;
use App\Models\DoiDepositItem;
use App\Models\Issue;
use App\Models\Journal;
use App\Models\JournalSection;
use App\Models\User;
use App\Models\Volume;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Queue::fake();
    Storage::fake('private');
});

/**
 * A journal with a volume, an issue and one article that would pass the publish gate.
 *
 * @return array{0: Journal, 1: Issue, 2: Article}
 */
function adminJournal(array $journalOverrides = [], array $articleOverrides = []): array
{
    $journal = Journal::factory()->create(array_merge([
        'abbreviation' => 'JCDMS',
        'doi_prefix' => '10.12345',
        'doi_suffix_pattern' => '{journal}.v{volume}i{issue}.{seq}',
    ], $journalOverrides));

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
    ], $articleOverrides));

    ArticleAuthor::factory()->create(['article_id' => $article->id, 'sequence' => 1]);
    ArticleFile::factory()->create(['article_id' => $article->id, 'type' => ArticleFileType::Pdf]);

    return [$journal, $issue->fresh(), $article->fresh()];
}

describe('the publish gate', function () {
    it('lets an editor publish an article', function () {
        [$journal, , $article] = adminJournal();

        $editor = grantRoleOn(User::factory()->create(), $journal, 'journal-editor');

        $this->actingAs($editor)
            ->post("/admin/articles/{$article->id}/publish")
            ->assertRedirect();

        $article->refresh();

        expect($article->status)->toBe(ArticleStatus::Published)
            ->and($article->doi_suffix)->toBe('jcdms.v10i2.001')
            ->and($article->doi())->toBe('10.12345/jcdms.v10i2.001');

        // Dispatched OUTSIDE the publish transaction — a Crossref outage cannot roll back a
        // publication that has already been announced.
        Queue::assertPushed(DepositToCrossref::class);
    });

    it('does NOT let production publish, though it may edit everything else', function () {
        // Publishing freezes a URL and spends money at Crossref minting an identifier that can
        // never be withdrawn. `production` prepares everything and makes nothing permanent.
        [$journal, $issue, $article] = adminJournal();

        $production = grantRoleOn(User::factory()->create(), $journal, 'production');

        $this->actingAs($production)
            ->post("/admin/articles/{$article->id}/publish")
            ->assertForbidden();

        $this->actingAs($production)
            ->post("/admin/issues/{$issue->id}/publish")
            ->assertForbidden();

        expect($article->fresh()->status)->toBe(ArticleStatus::Draft);
        Queue::assertNothingPushed();

        // But it CAN reach the editor, and it CAN save.
        $this->actingAs($production)
            ->get("/admin/articles/{$article->id}/edit")
            ->assertOk();
    });

    it('returns EVERY pre-flight failure at once, not the first', function () {
        // An editor fixing a publication one error at a time, against a deadline, is how a
        // half-complete article goes live.
        [$journal, , $article] = adminJournal(articleOverrides: [
            'abstract' => null,
            'first_page' => null,
            'last_page' => null,
        ]);

        $article->authors()->delete();
        $article->files()->delete();

        $editor = grantRoleOn(User::factory()->create(), $journal, 'journal-editor');

        $response = $this->actingAs($editor)
            ->from("/admin/articles/{$article->id}/edit")
            ->post("/admin/articles/{$article->id}/publish");

        $response->assertRedirect("/admin/articles/{$article->id}/edit");

        // All of them, in ONE response.
        $response->assertSessionHasErrors(['abstract', 'pdf', 'authors', 'pages']);

        // And the complete, flattened list the UI renders — every message, not merely the
        // first per key, which is all Inertia's own `errors` prop would carry.
        $flat = session('publishErrors');

        expect($flat)->toBeArray()
            ->and(count($flat))->toBeGreaterThanOrEqual(4);

        expect(implode(' ', $flat))
            ->toContain('abstract')
            ->toContain('PDF')
            ->toContain('author')
            ->toContain('page range');

        expect($article->fresh()->status)->toBe(ArticleStatus::Draft);
    });

    it('publishes an issue and everything in it', function () {
        [$journal, $issue] = adminJournal();

        $editor = grantRoleOn(User::factory()->create(), $journal, 'journal-editor');

        $this->actingAs($editor)
            ->post("/admin/issues/{$issue->id}/publish")
            ->assertRedirect();

        expect($issue->fresh()->isPublished())->toBeTrue();

        // ONE batch for the whole issue — one deposit row to retry, not N.
        Queue::assertPushed(DepositToCrossref::class, 1);
    });
});

describe('cross-journal isolation', function () {
    it('gives an editor of Journal A a 403 on every one of Journal B\'s admin screens', function () {
        [$journalA] = adminJournal();
        [$journalB, $issueB, $articleB] = adminJournal(['slug' => 'journal-b', 'abbreviation' => 'JB']);

        $editor = grantRoleOn(User::factory()->create(), $journalA, 'journal-editor');

        $this->actingAs($editor);

        $this->get("/admin/journals/{$journalB->id}/issues")->assertForbidden();
        $this->get("/admin/journals/{$journalB->id}/settings")->assertForbidden();
        $this->get("/admin/journals/{$journalB->id}/deposits")->assertForbidden();
        $this->get("/admin/journals/{$journalB->id}/users")->assertForbidden();
        $this->get("/admin/journals/{$journalB->id}/articles/create")->assertForbidden();
        $this->get("/admin/issues/{$issueB->id}")->assertForbidden();
        $this->get("/admin/articles/{$articleB->id}/edit")->assertForbidden();

        // And cannot spend Journal B's Crossref credits.
        $this->post("/admin/articles/{$articleB->id}/publish")->assertForbidden();
        $this->post("/admin/issues/{$issueB->id}/publish")->assertForbidden();

        expect($articleB->fresh()->status)->toBe(ArticleStatus::Draft);
    });

    it('keeps a reviewer and an author out of the admin entirely', function () {
        [$journal] = adminJournal();

        $reviewer = grantRoleOn(User::factory()->create(), $journal, 'reviewer');
        $author = grantRoleOn(User::factory()->create(), $journal, 'author');

        // Both carry `journal.view`. Neither has any business in the editorial admin.
        $this->actingAs($reviewer)->get('/admin')->assertForbidden();
        $this->actingAs($author)->get('/admin')->assertForbidden();
    });
});

describe('reordering an issue', function () {
    it('saves a new running order on a DRAFT issue', function () {
        [$journal, $issue, $first] = adminJournal();

        $second = Article::factory()->create([
            'journal_id' => $journal->id,
            'issue_id' => $issue->id,
            'sequence' => 2,
            'first_page' => 11,
            'last_page' => 20,
        ]);

        $editor = grantRoleOn(User::factory()->create(), $journal, 'journal-editor');

        $this->actingAs($editor)
            ->post("/admin/issues/{$issue->id}/reorder", ['order' => [$second->id, $first->id]])
            ->assertRedirect();

        expect($second->fresh()->sequence)->toBe(1)
            ->and($first->fresh()->sequence)->toBe(2);
    });

    it('REFUSES to reorder a published issue', function () {
        // The DOI suffix is derived from the position. Reordering changes which article a
        // minted, cited, permanently-resolving identifier refers to.
        [$journal, $issue, $first] = adminJournal();

        $second = Article::factory()->create([
            'journal_id' => $journal->id,
            'issue_id' => $issue->id,
            'sequence' => 2,
            'first_page' => 11,
            'last_page' => 20,
        ]);

        // The whole issue has to be publishable, or the gate refuses it and nothing is frozen.
        ArticleAuthor::factory()->create(['article_id' => $second->id, 'sequence' => 1]);
        ArticleFile::factory()->create(['article_id' => $second->id, 'type' => ArticleFileType::Pdf]);

        $editor = grantRoleOn(User::factory()->create(), $journal, 'journal-editor');

        $this->actingAs($editor)->post("/admin/issues/{$issue->id}/publish")->assertRedirect();

        expect($issue->fresh()->isPublished())->toBeTrue();

        $this->actingAs($editor)
            ->post("/admin/issues/{$issue->id}/reorder", ['order' => [$second->id, $first->id]])
            ->assertForbidden();

        // Untouched. Every citation already made to the issue still points where it did.
        expect($first->fresh()->sequence)->toBe(1)
            ->and($second->fresh()->sequence)->toBe(2);
    });
});

describe('the Crossref deposit log', function () {
    it('retries a failed deposit and REUSES the same deposit row', function () {
        [$journal, $issue, $article] = adminJournal();

        $deposit = DoiDeposit::factory()->failed()->create([
            'journal_id' => $journal->id,
            'issue_id' => $issue->id,
            'attempts' => 1,
        ]);

        DoiDepositItem::factory()->failed()->create([
            'doi_deposit_id' => $deposit->id,
            'article_id' => $article->id,
            'doi' => '10.12345/jcdms.v10i2.001',
        ]);

        $editor = grantRoleOn(User::factory()->create(), $journal, 'journal-editor');

        $this->actingAs($editor)
            ->post("/admin/deposits/{$deposit->id}/retry")
            ->assertRedirect();

        // ONE deposit, N attempts — not N deposits. The batch id is what Crossref's
        // submission report is keyed on, and it must not move.
        expect(DoiDeposit::count())->toBe(1);

        Queue::assertPushed(DepositToCrossref::class, function (DepositToCrossref $job) use ($deposit, $article): bool {
            return $job->depositId === $deposit->id
                && $job->journalId === $deposit->journal_id
                && $job->articleIds === [$article->id];
        });
    });

    it('streams the exact XML that was sent', function () {
        [$journal] = adminJournal();

        Storage::disk('private')->put('crossref/batch-1.xml', '<doi_batch>…</doi_batch>');

        $deposit = DoiDeposit::factory()->create([
            'journal_id' => $journal->id,
            'payload_path' => 'crossref/batch-1.xml',
            'status' => DepositStatus::Submitted,
        ]);

        $editor = grantRoleOn(User::factory()->create(), $journal, 'journal-editor');

        $this->actingAs($editor)
            ->get("/admin/deposits/{$deposit->id}/xml")
            ->assertOk()
            ->assertHeader('content-type', 'application/xml');
    });

    it('shows the endpoint, the attempts and Crossref\'s own words', function () {
        [$journal, $issue, $article] = adminJournal();

        $deposit = DoiDeposit::factory()->failed('Error: DOI prefix 10.12345 is not owned by this account')->create([
            'journal_id' => $journal->id,
            'issue_id' => $issue->id,
            'endpoint' => 'production',
        ]);

        DoiDepositItem::factory()->failed()->create([
            'doi_deposit_id' => $deposit->id,
            'article_id' => $article->id,
        ]);

        $editor = grantRoleOn(User::factory()->create(), $journal, 'journal-editor');

        $response = $this->actingAs($editor)->get("/admin/journals/{$journal->id}/deposits");

        $response->assertOk();

        $row = pageProps($response)['deposits']['data'][0];

        expect($row['endpoint'])->toBe('production')
            ->and($row['isProduction'])->toBeTrue()
            ->and($row['status'])->toBe('failed')
            ->and($row['isRetryable'])->toBeTrue()
            ->and($row['error'])->toContain('not owned by this account')
            ->and($row['items'])->toHaveCount(1);
    });
});

describe('the Crossref password', function () {
    it('NEVER appears in any admin response payload', function () {
        $secret = 'super-secret-crossref-password';

        [$journal, $issue, $article] = adminJournal([
            'crossref_username' => 'lcc_test',
            'crossref_password' => $secret,
        ]);

        // Even where Crossref echoes the credentials straight back at us in its own error.
        $deposit = DoiDeposit::factory()->failed("Authentication failed for login_passwd={$secret}")->create([
            'journal_id' => $journal->id,
            'issue_id' => $issue->id,
        ]);

        DoiDepositItem::factory()->failed("login_passwd={$secret} rejected")->create([
            'doi_deposit_id' => $deposit->id,
            'article_id' => $article->id,
        ]);

        $editor = grantRoleOn(User::factory()->create(), $journal, 'journal-editor');
        $this->actingAs($editor);

        $screens = [
            '/admin',
            "/admin/journals/{$journal->id}/issues",
            "/admin/journals/{$journal->id}/settings",
            "/admin/journals/{$journal->id}/deposits",
            "/admin/journals/{$journal->id}/users",
            "/admin/issues/{$issue->id}",
            "/admin/articles/{$article->id}/edit",
        ];

        foreach ($screens as $screen) {
            $response = $this->get($screen);
            $response->assertOk();

            // The props as they LEAVE THE SERVER, and the whole rendered payload. Nothing
            // hides from a substring search.
            expect(pagePropsJson($response))->not->toContain($secret);
            expect($response->getContent())->not->toContain($secret);
        }
    });

    it('sends a "set" indicator to the settings screen, and never the value', function () {
        [$journal] = adminJournal(['crossref_password' => 'another-secret']);

        $editor = grantRoleOn(User::factory()->create(), $journal, 'journal-editor');

        $response = $this->actingAs($editor)->get("/admin/journals/{$journal->id}/settings");

        $settings = pageProps($response)['settings'];

        expect($settings['crossref_password_set'])->toBeTrue()
            ->and($settings)->not->toHaveKey('crossref_password');
    });

    it('leaves the stored password alone when the replace field is empty', function () {
        // An empty password box is what a browser autofill, a half-finished edit and a stray
        // keystroke all look like. None of them may wipe a working credential.
        [$journal] = adminJournal(['crossref_password' => 'keep-me']);

        $editor = grantRoleOn(User::factory()->create(), $journal, 'journal-editor');

        $this->actingAs($editor)
            ->put("/admin/journals/{$journal->id}/settings", [
                'title' => $journal->title,
                'publisher' => 'London Churchill College',
                'doi_suffix_pattern' => '{journal}.v{volume}i{issue}.{seq}',
                'doi_sequence_padding' => 3,
                'crossref_password' => '',
                'sections' => [],
            ])
            ->assertRedirect();

        expect($journal->fresh()->crossref_password)->toBe('keep-me');
    });
});

describe('the article editor', function () {
    it('saves metadata, authors and references in ONE request', function () {
        [$journal, $issue] = adminJournal();

        $editor = grantRoleOn(User::factory()->create(), $journal, 'journal-editor');

        $this->actingAs($editor)
            ->post("/admin/journals/{$journal->id}/articles", [
                'title' => 'Navigating the transition from teaching to educational leadership',
                'slug' => 'navigating-the-transition',
                'abstract' => 'A protocol.',
                'keywords' => ['leadership', 'transition'],
                'issue_id' => $issue->id,
                'sequence' => 4,
                'first_page' => 59,
                'last_page' => 79,
                'authors' => [
                    ['given_name' => 'Nick', 'family_name' => 'Papé', 'orcid' => '0000-0003-1395-3751', 'is_corresponding' => true],
                    ['given_name' => 'Russell', 'family_name' => 'Kabir', 'orcid' => null, 'is_corresponding' => false],
                ],
                'references' => [
                    ['raw_text' => 'Smith, J. (2020). A paper.', 'doi' => '10.1000/xyz'],
                    ['raw_text' => 'Jones, A. (2021). Another.', 'doi' => null],
                ],
                'pdf' => UploadedFile::fake()->create('article.pdf', 200, 'application/pdf'),
            ])
            ->assertRedirect();

        $article = Article::where('slug', 'navigating-the-transition')->firstOrFail();

        expect($article->authors)->toHaveCount(2)
            ->and($article->references)->toHaveCount(2)
            ->and($article->hasPdf())->toBeTrue()
            ->and($article->status)->toBe(ArticleStatus::Draft);

        // Author ORDER is meaningful — it is the contribution order and Crossref deposits it.
        expect($article->authors->pluck('family_name')->all())->toBe(['Papé', 'Kabir']);

        // An ORCID exists only where a real one was given. Never invented for the other author.
        expect($article->authors->firstWhere('family_name', 'Kabir')->orcid)->toBeNull();
    });

    it('refuses a malformed ORCID rather than storing it', function () {
        [$journal] = adminJournal();

        $editor = grantRoleOn(User::factory()->create(), $journal, 'journal-editor');

        $this->actingAs($editor)
            ->post("/admin/journals/{$journal->id}/articles", [
                'title' => 'A paper',
                'slug' => 'a-paper',
                'authors' => [
                    ['given_name' => 'A', 'family_name' => 'Person', 'orcid' => '1234-5678'],
                ],
            ])
            ->assertSessionHasErrors('authors.0.orcid');

        expect(Article::where('slug', 'a-paper')->exists())->toBeFalse();
    });

    it('refuses named authors AND a corporate author together', function () {
        [$journal] = adminJournal();

        $editor = grantRoleOn(User::factory()->create(), $journal, 'journal-editor');

        $this->actingAs($editor)
            ->post("/admin/journals/{$journal->id}/articles", [
                'title' => 'An editorial',
                'slug' => 'an-editorial',
                'corporate_author' => 'Members of the CLIR, London Churchill College',
                'authors' => [
                    ['given_name' => 'A', 'family_name' => 'Person'],
                ],
            ])
            ->assertSessionHasErrors('corporate_author');
    });

    it('does not accept a slug or a sequence for a PUBLISHED article', function () {
        [$journal, , $article] = adminJournal();

        $editor = grantRoleOn(User::factory()->create(), $journal, 'journal-editor');

        $this->actingAs($editor)->post("/admin/articles/{$article->id}/publish")->assertRedirect();

        $published = $article->fresh();
        $slug = $published->slug;

        // A title correction is legitimate. It must NOT drag the slug with it — that is the
        // exact failure ArticleObserver exists to prevent, and a changed URL is a dead DOI.
        $this->actingAs($editor)
            ->post("/admin/articles/{$article->id}", [
                'title' => 'A corrected title',
                'slug' => 'a-completely-different-slug',
                'sequence' => 99,
                'abstract' => $published->abstract,
                'authors' => [['given_name' => 'A', 'family_name' => 'Person']],
            ])
            ->assertRedirect();

        $after = $article->fresh();

        expect($after->title)->toBe('A corrected title')
            ->and($after->slug)->toBe($slug)
            ->and($after->sequence)->toBe(1)
            ->and($after->doi_suffix)->toBe('jcdms.v10i2.001');
    });
});

describe('the admin dashboard', function () {
    it('says plainly that a journal with no prefix cannot register DOIs', function () {
        // The deliberate NULL. Not an error, not a misconfiguration — the true state of a
        // journal Crossref has not registered yet.
        [$journal] = adminJournal(['doi_prefix' => null]);

        $editor = grantRoleOn(User::factory()->create(), $journal, 'journal-editor');

        $response = $this->actingAs($editor)->get('/admin');

        $response->assertOk();

        $overview = collect(pageProps($response)['journals'])->firstWhere('id', $journal->id);

        // The page says so in words (Admin/Dashboard.tsx). The PROPS are what can be asserted
        // here — the tests run withoutVite, so there is no SSR process to render the sentence,
        // and asserting on rendered React would be asserting on a bundle that does not exist.
        expect($overview['doi']['canMintDois'])->toBeFalse()
            ->and($overview['doi']['prefix'])->toBeNull();
    });

    it('404s the issues screen for a continuous journal', function () {
        // A continuous journal has no issues at all. Not a disabled tab — nothing.
        $journal = Journal::factory()->continuous()->create();

        $editor = grantRoleOn(User::factory()->create(), $journal, 'journal-editor');

        $this->actingAs($editor)
            ->get("/admin/journals/{$journal->id}/issues")
            ->assertNotFound();
    });

    it('requires a login', function () {
        $this->get('/admin')->assertRedirect('/login');
    });
});

describe('per-journal roles', function () {
    it('assigns a role ON THIS JOURNAL ONLY', function () {
        [$journalA] = adminJournal();
        [$journalB] = adminJournal(['slug' => 'journal-b', 'abbreviation' => 'JB']);

        $editor = grantRoleOn(User::factory()->create(), $journalA, 'journal-editor');
        $person = User::factory()->create();

        $this->actingAs($editor)
            ->put("/admin/journals/{$journalA->id}/users/{$person->id}", ['roles' => ['section-editor']])
            ->assertRedirect();

        // On A, yes. On B, nothing — which is the entire reason Spatie teams exist here.
        expect($person->fresh()->can('manageArticles', $journalA))->toBeTrue()
            ->and($person->fresh()->can('manageArticles', $journalB))->toBeFalse()
            ->and($person->fresh()->can('publish', $journalA))->toBeFalse();
    });

    it('removes someone from a journal when their roles are emptied', function () {
        [$journal] = adminJournal();

        $editor = grantRoleOn(User::factory()->create(), $journal, 'journal-editor');
        $person = grantRoleOn(User::factory()->create(), $journal, 'section-editor');

        $this->actingAs($editor)
            ->put("/admin/journals/{$journal->id}/users/{$person->id}", ['roles' => []])
            ->assertRedirect();

        expect($person->fresh()->can('manageArticles', $journal))->toBeFalse();
    });
});
