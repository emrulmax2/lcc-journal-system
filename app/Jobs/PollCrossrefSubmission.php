<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\DepositItemStatus;
use App\Enums\DepositStatus;
use App\Models\DoiDeposit;
use App\Services\Crossref\CrossrefDepositor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use SimpleXMLElement;

/**
 * Asks Crossref what ACTUALLY happened.
 *
 * The deposit POST returning 200 means only that Crossref accepted the file for
 * processing. It processes asynchronously, and it can accept a batch while rejecting
 * individual records inside it — a bad ORCID, a malformed resource URL, a DOI whose
 * prefix we do not own. Without this job, those DOIs sit in our database marked
 * "submitted" forever, and we believe we have identifiers that do not resolve.
 *
 * So nothing is `registered` until Crossref's own submission report says so, per DOI.
 */
class PollCrossrefSubmission implements ShouldQueue
{
    use Queueable;

    public int $tries = 8;

    /** Crossref can take minutes to hours under load. Back off patiently, don't hammer. */
    public array $backoff = [120, 300, 900, 1800, 3600];

    public function __construct(public readonly int $depositId) {}

    public function handle(CrossrefDepositor $depositor): void
    {
        $deposit = DoiDeposit::with(['journal', 'items'])->find($this->depositId);

        if ($deposit === null || $deposit->status->isTerminal()) {
            return;
        }

        $report = $depositor->fetchSubmissionReport($deposit->journal, $deposit->batch_id);

        $deposit->update(['response_body' => $report]);

        $parsed = $this->parse($report);

        if ($parsed === null) {
            // Not ready yet. Throwing re-queues with backoff rather than silently marking
            // the deposit as done — "we couldn't tell" must never be recorded as success.
            throw new \RuntimeException('Crossref submission report not available yet for batch '.$deposit->batch_id);
        }

        foreach ($deposit->items as $item) {
            $outcome = $parsed[$item->doi] ?? null;

            if ($outcome === null) {
                continue;
            }

            $item->update([
                'status' => $outcome['success'] ? DepositItemStatus::Registered : DepositItemStatus::Failed,
                // Crossref's ACTUAL words. Paraphrasing them here would strip the one
                // thing that makes a failed deposit diagnosable.
                'message' => $outcome['message'],
            ]);
        }

        $deposit->refresh();

        $failed = $deposit->items()->where('status', DepositItemStatus::Failed)->count();
        $pending = $deposit->items()->where('status', DepositItemStatus::Pending)->count();

        if ($pending > 0) {
            throw new \RuntimeException('Crossref has not yet reported on every DOI in batch '.$deposit->batch_id);
        }

        $deposit->update([
            'status' => $failed > 0 ? DepositStatus::Failed : DepositStatus::Registered,
            'error_message' => $failed > 0 ? "{$failed} DOI(s) were rejected by Crossref." : null,
            'completed_at' => now(),
        ]);
    }

    /**
     * @return array<string, array{success: bool, message: string}>|null
     *                                                                   NULL when the report is not yet available.
     */
    private function parse(string $report): ?array
    {
        if (blank($report) || ! str_contains($report, '<')) {
            return null;
        }

        try {
            $xml = new SimpleXMLElement($report);
        } catch (\Throwable) {
            return null;
        }

        $results = [];

        // Crossref's report nests record_diagnostic elements under batch_data/record_diagnostic.
        foreach ($xml->xpath('//record_diagnostic') ?: [] as $record) {
            $doi = trim((string) $record->doi);

            if ($doi === '') {
                continue;
            }

            $status = strtolower((string) $record['status']);

            $results[$doi] = [
                'success' => $status === 'success',
                'message' => trim((string) $record->msg) ?: ucfirst($status),
            ];
        }

        return $results === [] ? null : $results;
    }
}
