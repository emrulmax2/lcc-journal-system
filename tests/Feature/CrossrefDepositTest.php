<?php

declare(strict_types=1);

use App\Enums\ArticleFileType;
use App\Enums\ArticleStatus;
use App\Enums\DepositStatus;
use App\Jobs\DepositToCrossref;
use App\Jobs\PollCrossrefSubmission;
use App\Models\Article;
use App\Models\ArticleAuthor;
use App\Models\ArticleFile;
use App\Models\DoiDeposit;
use App\Models\Issue;
use App\Models\Journal;
use App\Models\JournalSection;
use App\Models\Volume;
use App\Services\Crossref\CrossrefDepositor;
use App\Services\Crossref\CrossrefXmlBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

/**
 * Validate against the REAL Crossref XSD, cached in tests/fixtures/crossref/.
 *
 * Not a hand-rolled approximation of it. Element ORDER is significant in this schema and
 * Crossref's rejection messages do not tell you which element is at fault — so a test
 * that only checks "does it contain <doi>" would pass happily while producing a document
 * Crossref refuses.
 */
function assertValidCrossrefXml(string $xml): void
{
    // Out of process. Compiling Crossref's real 21-file schema graph inside a booted
    // Laravel test process segfaults libxml on this platform — see the header of
    // tests/fixtures/crossref/validate.php. The alternative was to delete the assertion,
    // which would leave us generating deposit XML that nothing checks until Crossref
    // rejects it with a message that does not say which element is wrong.
    $tmp = tempnam(sys_get_temp_dir(), 'crossref_').'.xml';
    file_put_contents($tmp, $xml);

    $validator = base_path('tests/fixtures/crossref/validate.php');

    $process = Process::fromShellCommandline(
        sprintf('php -d memory_limit=1G %s %s', escapeshellarg($validator), escapeshellarg($tmp))
    );
    $process->setTimeout(120);
    $process->run();

    $output = trim($process->getOutput().$process->getErrorOutput());

    @unlink($tmp);

    expect($process->getExitCode())->toBe(
        0,
        "Generated XML failed validation against the REAL Crossref 5.3.1 XSD:\n  ".
        str_replace("\n", "\n  ", $output)
    );
}

function jcdmsIssueReadyToDeposit(): Issue
{
    $journal = Journal::factory()->create([
        'slug' => 'jcdms',
        'title' => 'Journal of Contemporary Development & Management Studies',
        'abbreviation' => 'JCD&MS',
        'publisher' => 'London Churchill College',
        'doi_prefix' => '10.12345',
        'issn_online' => '2755-0001',
        'crossref_username' => 'lcc_test',
        'crossref_password' => 'secret-password',
        'crossref_deposit_references' => true,
    ]);

    $section = JournalSection::factory()->create([
        'journal_id' => $journal->id, 'name' => 'Research Protocol', 'doi_eligible' => true,
    ]);

    $volume = Volume::factory()->create(['journal_id' => $journal->id, 'number' => 10, 'year' => 2026]);
    $issue = Issue::factory()->published()->create([
        'volume_id' => $volume->id, 'number' => 2, 'publication_date' => '2026-03-01',
    ]);

    $article = Article::factory()->published()->create([
        'journal_id' => $journal->id,
        'issue_id' => $issue->id,
        'journal_section_id' => $section->id,
        'sequence' => 5,
        'slug' => 'transition-teaching-educational-leadership',
        'doi_suffix' => 'jcdms.v10i2.005',
        'title' => 'Research Protocol: Navigating the Transition from Teaching to Educational Leadership',
        'first_page' => 59,
        'last_page' => 79,
        'published_at' => '2026-03-01',
    ]);

    foreach ([
        ['Nick', 'Papé', '0000-0003-1395-3751', 'London Churchill College, UK'],
        ['Russell', 'Kabir', '0000-0002-4510-0385', 'Anglia Ruskin University, UK'],
    ] as $i => [$given, $family, $orcid, $affiliation]) {
        ArticleAuthor::factory()->create([
            'article_id' => $article->id,
            'given_name' => $given, 'family_name' => $family,
            'orcid' => $orcid, 'affiliation' => $affiliation,
            'sequence' => $i + 1,
        ]);
    }

    ArticleFile::factory()->create([
        'article_id' => $article->id, 'type' => ArticleFileType::Pdf,
    ]);

    return $issue->fresh(['volume.journal', 'articles.authors', 'articles.section', 'articles.references']);
}

describe('XML builder', function () {
    it('produces XML that validates against the real Crossref 5.3.1 XSD', function () {
        $issue = jcdmsIssueReadyToDeposit();

        $xml = app(CrossrefXmlBuilder::class)->build(
            $issue->journal,
            $issue->articles,
            $issue,
            'batch-0001',
        );

        assertValidCrossrefXml($xml);
    });

    it('deposits the DOI and the landing page URL that the article actually resolves to', function () {
        $issue = jcdmsIssueReadyToDeposit();
        $article = $issue->articles->first();

        $xml = app(CrossrefXmlBuilder::class)->build($issue->journal, $issue->articles, $issue, 'batch-0001');

        // The deposited resource URL and the advertised citation_abstract_html_url come
        // from the SAME accessor, so a DOI can never resolve somewhere the meta tags
        // don't point.
        expect($xml)
            ->toContain('<doi>10.12345/jcdms.v10i2.005</doi>')
            ->toContain('<resource>'.htmlspecialchars($article->landingUrl()).'</resource>');
    });

    it('emits a corporate author as <organization>, never as a person_name', function () {
        // The CLIR editorial. A naive implementation deposits a surname of "College" and
        // permanently attaches it to the DOI.
        $issue = jcdmsIssueReadyToDeposit();
        $article = $issue->articles->first();
        $article->authors()->delete();
        $article->update([
            'corporate_author' => 'Members of the Centre for Learning Innovation and Research (CLIR), London Churchill College',
        ]);

        $issue = $issue->fresh(['volume.journal', 'articles.authors', 'articles.section', 'articles.references']);
        $xml = app(CrossrefXmlBuilder::class)->build($issue->journal, $issue->articles, $issue, 'batch-0001');

        expect($xml)
            ->toContain('<organization sequence="first" contributor_role="author">Members of the Centre for Learning Innovation and Research (CLIR), London Churchill College</organization>')
            ->not->toContain('<person_name');

        assertValidCrossrefXml($xml);
    });

    it('marks the first author "first" and the rest "additional", preserving author order', function () {
        $issue = jcdmsIssueReadyToDeposit();
        $xml = app(CrossrefXmlBuilder::class)->build($issue->journal, $issue->articles, $issue, 'batch-0001');

        expect($xml)->toMatch('/sequence="first"[^>]*>\s*<given_name>Nick<\/given_name>\s*<surname>Papé<\/surname>/s');
        expect($xml)->toMatch('/sequence="additional"[^>]*>\s*<given_name>Russell<\/given_name>\s*<surname>Kabir<\/surname>/s');
    });

    it('includes an ORCID only where a real one exists — never a fabricated one', function () {
        $issue = jcdmsIssueReadyToDeposit();
        $issue->articles->first()->authors()->where('family_name', 'Kabir')->update(['orcid' => null]);

        $issue = $issue->fresh(['volume.journal', 'articles.authors', 'articles.section', 'articles.references']);
        $xml = app(CrossrefXmlBuilder::class)->build($issue->journal, $issue->articles, $issue, 'batch-0001');

        expect(substr_count($xml, '<ORCID>'))->toBe(1)
            ->and($xml)->toContain('<ORCID>https://orcid.org/0000-0003-1395-3751</ORCID>');

        assertValidCrossrefXml($xml);
    });

    it('omits the ISSN entirely while the British Library has not issued one', function () {
        // A "0000-0000" placeholder would be deposited as fact and propagate everywhere.
        $issue = jcdmsIssueReadyToDeposit();
        $issue->journal->update(['issn_online' => null, 'issn_print' => null]);

        $xml = app(CrossrefXmlBuilder::class)->build($issue->journal->fresh(), $issue->articles, $issue, 'batch-0001');

        expect($xml)->not->toContain('<issn');
        assertValidCrossrefXml($xml);
    });

    it('omits journal_issue for a continuous-publication journal', function () {
        $journal = Journal::factory()->continuous()->withDoiPrefix()->create();
        $section = JournalSection::factory()->create(['journal_id' => $journal->id]);

        $article = Article::factory()->published()->continuous()->create([
            'journal_id' => $journal->id,
            'journal_section_id' => $section->id,
            'doi_suffix' => 'mrdn.2026.00412',
            'published_at' => '2026-06-28',
        ]);
        ArticleAuthor::factory()->create(['article_id' => $article->id, 'sequence' => 1]);

        $articles = Article::with(['journal', 'authors', 'section', 'references'])->whereKey($article->id)->get();
        $xml = app(CrossrefXmlBuilder::class)->build($journal, $articles, null, 'batch-0001');

        expect($xml)->not->toContain('<journal_issue>');
        assertValidCrossrefXml($xml);
    });

    it('skips sections that are not DOI-eligible', function () {
        // Front matter gets no DOI. Depositing it would mint identifiers for things that
        // should not be citable.
        $issue = jcdmsIssueReadyToDeposit();
        $issue->articles->first()->section->update(['doi_eligible' => false]);

        $issue = $issue->fresh(['volume.journal', 'articles.authors', 'articles.section', 'articles.references']);

        expect(fn () => app(CrossrefXmlBuilder::class)->build($issue->journal, $issue->articles, $issue, 'batch-0001'))
            ->toThrow(RuntimeException::class, 'No DOI-eligible articles');
    });

    it('refuses to build anything while the journal has no DOI prefix', function () {
        $issue = jcdmsIssueReadyToDeposit();
        $issue->journal->update(['doi_prefix' => null]);

        expect(fn () => app(CrossrefXmlBuilder::class)->build($issue->journal->fresh(), $issue->articles, $issue, 'batch-0001'))
            ->toThrow(RuntimeException::class, 'no Crossref DOI prefix');
    });
});

describe('the deposit job', function () {
    beforeEach(function () {
        Storage::fake('private');

        // Queue::fake() so PollCrossrefSubmission does not run. The test queue driver is
        // `sync`, which ignores ->delay() and executes the poll immediately — i.e. it asks
        // Crossref for a submission report a few microseconds after submitting, which in
        // reality would never be ready. Faking the queue lets us assert the deposit's own
        // outcome, and assert separately that the poll was scheduled.
        Queue::fake([PollCrossrefSubmission::class]);
    });

    it('records a SUBMITTED deposit on a 200 — not registered, because 200 is not registration', function () {
        Http::fake(['*' => Http::response('<html><body><h2>SUCCESS</h2></body></html>', 200)]);

        $issue = jcdmsIssueReadyToDeposit();

        (new DepositToCrossref(
            $issue->journal->id,
            $issue->articles->pluck('id')->all(),
            $issue->id,
        ))->handle(app(CrossrefXmlBuilder::class), app(CrossrefDepositor::class));

        $deposit = DoiDeposit::first();

        // Crossref processes asynchronously. Calling this "registered" is how a journal
        // ends up believing it has DOIs that were in fact rejected.
        expect($deposit->status)->toBe(DepositStatus::Submitted)
            ->and($deposit->status)->not->toBe(DepositStatus::Registered);

        expect($deposit->items)->toHaveCount(1)
            ->and($deposit->items->first()->doi)->toBe('10.12345/jcdms.v10i2.005');
    });

    it('keeps the exact XML it sent, for when Crossref rejects it six months later', function () {
        Http::fake(['*' => Http::response('SUCCESS', 200)]);

        $issue = jcdmsIssueReadyToDeposit();

        (new DepositToCrossref($issue->journal->id, $issue->articles->pluck('id')->all(), $issue->id))
            ->handle(app(CrossrefXmlBuilder::class), app(CrossrefDepositor::class));

        $deposit = DoiDeposit::first();

        expect($deposit->payload_path)->not->toBeNull();
        Storage::disk('private')->assertExists($deposit->payload_path);
        expect(Storage::disk('private')->get($deposit->payload_path))->toContain('<doi>10.12345/jcdms.v10i2.005</doi>');
    });

    it('defaults to the SANDBOX endpoint, so a misconfigured env cannot spend money', function () {
        Http::fake(['*' => Http::response('SUCCESS', 200)]);
        config(['crossref.endpoint' => 'sandbox']);

        $issue = jcdmsIssueReadyToDeposit();

        (new DepositToCrossref($issue->journal->id, $issue->articles->pluck('id')->all(), $issue->id))
            ->handle(app(CrossrefXmlBuilder::class), app(CrossrefDepositor::class));

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://test.crossref.org/'));
        expect(DoiDeposit::first()->endpoint)->toBe('sandbox');
    });

    it('falls back to SANDBOX, never production, when the endpoint config is a typo', function () {
        Http::fake(['*' => Http::response('SUCCESS', 200)]);
        config(['crossref.endpoint' => 'prodcution']);   // typo, on purpose

        $issue = jcdmsIssueReadyToDeposit();

        (new DepositToCrossref($issue->journal->id, $issue->articles->pluck('id')->all(), $issue->id))
            ->handle(app(CrossrefXmlBuilder::class), app(CrossrefDepositor::class));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'test.crossref.org'));
    });

    it('records Crossref\'s real error message on failure, and stays retryable', function () {
        Http::fake(['*' => Http::response('<html><body>Error: DOI prefix 10.12345 is not owned by this account</body></html>', 401)]);

        $issue = jcdmsIssueReadyToDeposit();

        $job = new DepositToCrossref($issue->journal->id, $issue->articles->pluck('id')->all(), $issue->id);

        expect(fn () => $job->handle(app(CrossrefXmlBuilder::class), app(CrossrefDepositor::class)))
            ->toThrow(RuntimeException::class);

        $deposit = DoiDeposit::first();

        expect($deposit->status)->toBe(DepositStatus::Failed)
            ->and($deposit->status->isRetryable())->toBeTrue()
            // Crossref's actual words, not a paraphrase. This is the only thing that makes
            // a failed deposit diagnosable from the admin UI.
            ->and($deposit->error_message)->toContain('not owned by this account');
    });
});

describe('publish is decoupled from Crossref', function () {
    it('keeps articles LIVE even when Crossref is completely unreachable', function () {
        // The acceptance test from the architecture doc. A Crossref outage must never be
        // able to take the public site down, or roll back a publication.
        Storage::fake('private');
        Queue::fake([PollCrossrefSubmission::class]);

        // A stub depositor rather than Http::fake(fn () => throw new ConnectionException).
        // Throwing from inside an Http::fake callback SEGFAULTS PHP on this platform —
        // it is a crash in the fake, not in our code, and it takes the whole suite down
        // with no message. Swapping the service is a cleaner simulation anyway: it is the
        // depositor, not the HTTP client, whose failure we care about.
        $this->swap(CrossrefDepositor::class, new class extends CrossrefDepositor
        {
            public function deposit(Journal $journal, string $xml, string $batchId): Response
            {
                throw new ConnectionException('Connection timed out after 60000 milliseconds');
            }
        });

        $issue = jcdmsIssueReadyToDeposit();
        $article = $issue->articles->first();

        // Simulate the queued deposit blowing up AFTER publication.
        try {
            (new DepositToCrossref($issue->journal->id, [$article->id], $issue->id))
                ->handle(app(CrossrefXmlBuilder::class), app(CrossrefDepositor::class));
        } catch (Throwable) {
            // expected — the job fails and the queue will retry it later
        }

        // The pages are still live, and still fully machine-readable.
        expect($article->fresh()->status)->toBe(ArticleStatus::Published);

        $response = $this->get("/articles/{$article->slug}");
        $response->assertOk();
        expect($response->getContent())->toContain('citation_doi');

        // And the failed deposit is sitting there, retryable, with a reason.
        expect(DoiDeposit::first()->status)->toBe(DepositStatus::Failed);
    });
});
