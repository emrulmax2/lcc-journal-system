<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\DepositItemStatus;
use App\Enums\DepositStatus;
use App\Models\Article;
use App\Models\DoiDeposit;
use App\Models\Issue;
use App\Models\Journal;
use App\Services\Crossref\CrossrefDepositor;
use App\Services\Crossref\CrossrefXmlBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Deposits DOIs to Crossref, asynchronously and OUTSIDE the publish transaction.
 *
 * If this job fails — Crossref is down, credentials have lapsed, the XML is rejected —
 * the articles are ALREADY LIVE and stay live. The public site never depends on Crossref
 * being reachable. The editor sees the failure in the deposit log, with Crossref's actual
 * words, and retries.
 *
 * RETRY IS ALWAYS SAFE. Crossref is idempotent on the DOI: redepositing updates the
 * record rather than duplicating it. Do NOT add an "already deposited?" guard — that
 * would block the legitimate case of correcting metadata on a published article, which
 * is the only mechanism there is for fixing a mistake.
 *
 * NOTE the constructor takes IDs, not models. A serialised model would carry the
 * journal's encrypted Crossref password into the jobs table, where it would sit in
 * plaintext-at-rest inside a payload that gets logged, backed up and copied to staging.
 */
class DepositToCrossref implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** Exponential-ish backoff: Crossref outages are usually minutes, not seconds. */
    public array $backoff = [60, 300, 900, 3600];

    /**
     * @param  list<int>  $articleIds
     */
    public function __construct(
        public readonly int $journalId,
        public readonly array $articleIds,
        public readonly ?int $issueId = null,
        public readonly ?int $depositId = null,   // set when RETRYING an existing deposit
    ) {}

    public function handle(CrossrefXmlBuilder $builder, CrossrefDepositor $depositor): void
    {
        $journal = Journal::findOrFail($this->journalId);

        $articles = Article::query()
            ->whereIn('id', $this->articleIds)
            ->with(['journal', 'authors', 'section', 'issue.volume', 'references'])
            ->get();

        if ($articles->isEmpty()) {
            return;
        }

        $issue = $this->issueId !== null ? Issue::with('volume')->find($this->issueId) : null;

        $deposit = $this->deposit($journal, $issue);
        $batchId = $deposit->batch_id;

        try {
            $xml = $builder->build($journal, $articles, $issue, $batchId);

            // Keep the EXACT bytes we sent. When Crossref rejects a deposit six months
            // later, "what did we actually send?" is the first question, and a rebuilt
            // guess is not an answer.
            $path = "crossref/{$batchId}.xml";
            Storage::disk('private')->put($path, $xml);

            $deposit->update([
                'status' => DepositStatus::Depositing,
                'payload_path' => $path,
                'endpoint' => $depositor->isProduction() ? 'production' : 'sandbox',
                'attempts' => $deposit->attempts + 1,
                'submitted_at' => now(),
            ]);

            $this->syncItems($deposit, $articles);

            $response = $depositor->deposit($journal, $xml, $batchId);

            $deposit->update([
                'response_body' => $response->body(),
                // NOT `registered`. A 200 means Crossref ACCEPTED THE FILE FOR PROCESSING.
                // Registration is confirmed only by the submission report, which
                // PollCrossrefSubmission fetches.
                'status' => $response->successful() ? DepositStatus::Submitted : DepositStatus::Failed,
                'error_message' => $response->successful() ? null : $this->summarise($response->body()),
            ]);

            if (! $response->successful()) {
                // Throw so the queue retries with backoff. The deposit row already records
                // why, so the editor can see it without reading the queue.
                throw new \RuntimeException(
                    "Crossref rejected the deposit (HTTP {$response->status()}): ".$this->summarise($response->body())
                );
            }

            PollCrossrefSubmission::dispatch($deposit->id)->delay(now()->addMinutes(2));
        } catch (Throwable $e) {
            $deposit->update([
                'status' => DepositStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);

            // Never let the password reach a log line, an exception tracker or a Slack
            // alert. The message is safe; the journal object is not.
            Log::error('Crossref deposit failed', [
                'journal' => $journal->slug,
                'batch_id' => $batchId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /** Reuse the deposit row on retry, so the log shows one deposit with N attempts. */
    private function deposit(Journal $journal, ?Issue $issue): DoiDeposit
    {
        if ($this->depositId !== null) {
            return DoiDeposit::findOrFail($this->depositId);
        }

        return DoiDeposit::create([
            'journal_id' => $journal->id,
            'issue_id' => $issue?->id,
            'batch_id' => (string) Str::uuid(),
            'status' => DepositStatus::Queued,
        ]);
    }

    /** @param  Collection<int, Article>  $articles */
    private function syncItems(DoiDeposit $deposit, $articles): void
    {
        foreach ($articles as $article) {
            if ($article->doi() === null) {
                continue;
            }

            $deposit->items()->updateOrCreate(
                ['article_id' => $article->id],
                [
                    // The full DOI as sent, frozen. It duplicates Article::doi() on purpose:
                    // this is an audit record of what we deposited, and it must survive the
                    // article being edited afterwards.
                    'doi' => $article->doi(),
                    'status' => DepositItemStatus::Pending,
                    'message' => null,
                ],
            );
        }
    }

    private function summarise(string $body): string
    {
        return str(strip_tags($body))->squish()->limit(500)->toString();
    }
}
