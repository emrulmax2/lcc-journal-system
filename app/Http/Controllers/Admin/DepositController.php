<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\DepositStatus;
use App\Http\Controllers\Controller;
use App\Jobs\DepositToCrossref;
use App\Models\Article;
use App\Models\DoiDeposit;
use App\Models\DoiDepositItem;
use App\Models\Journal;
use App\Support\AdminChrome;
use App\Support\DepositRedactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Crossref deposit log — the only place a failed DOI registration is visible, and the
 * only place it can be fixed.
 *
 * THE ENDPOINT IS SHOUTED, not whispered. `sandbox` and `production` deposits look
 * identical in every other respect, and a journal that believes it has registered fifty
 * DOIs against test.crossref.org has fifty DOIs that resolve nowhere. So the endpoint is on
 * every row, in words.
 *
 * RETRY REUSES THE DEPOSIT ROW. It re-dispatches DepositToCrossref with the existing
 * depositId, so the log shows ONE deposit with N attempts rather than N deposits — and the
 * batch_id, which is what Crossref's submission report is keyed on, stays the same.
 *
 * Retry is ALWAYS safe: Crossref is idempotent on the DOI, and redepositing UPDATES the
 * record. There is deliberately no "already deposited?" guard — that would block the only
 * mechanism there is for correcting metadata on a published article.
 */
final class DepositController extends Controller
{
    public function index(Request $request, Journal $journal): Response
    {
        $this->authorize('depositDois', $journal);

        $deposits = $journal->deposits()
            ->with(['issue.volume', 'items.article', 'submittedBy'])
            ->orderByDesc('id')
            ->paginate(20);

        return Inertia::render('Admin/Registrations', array_merge(
            AdminChrome::for($request->user(), $journal),
            [
                'deposits' => [
                    'data' => collect($deposits->items())
                        ->map(fn (DoiDeposit $deposit): array => $this->present($deposit, $journal))
                        ->all(),
                    'links' => $deposits->linkCollection()->values()->all(),
                    'total' => $deposits->total(),
                ],

                'meta' => [
                    'title' => 'DOI registration — '.$journal->title,
                    'description' => 'Every Crossref deposit, its outcome and Crossref\'s own words.',
                ],
            ],
        ));
    }

    /**
     * Re-dispatch the SAME deposit. Same row, same batch id, attempts + 1.
     */
    public function retry(Request $request, DoiDeposit $deposit): RedirectResponse
    {
        $deposit->load(['journal', 'items']);

        $this->authorize('depositDois', $deposit->journal);

        $articleIds = $deposit->items->pluck('article_id')->unique()->values()->all();

        // A deposit that failed before it ever wrote its items has none. Fall back to the
        // issue it was for, so a first-attempt failure is still retryable — otherwise the
        // row is a dead end and the only way out is to republish.
        if ($articleIds === [] && $deposit->issue_id !== null) {
            $articleIds = Article::query()
                ->where('issue_id', $deposit->issue_id)
                ->pluck('id')
                ->all();
        }

        if ($articleIds === []) {
            return back()->with('error', 'This deposit has no articles to send. Republish the issue to rebuild it.');
        }

        // The existing depositId is what makes the job REUSE this row rather than create a
        // second one. See DepositToCrossref::deposit().
        DepositToCrossref::dispatch(
            $deposit->journal_id,
            $articleIds,
            $deposit->issue_id,
            $deposit->id,
        );

        $deposit->update([
            'status' => DepositStatus::Queued,
            'submitted_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Deposit queued again. Crossref updates the record — a retry is always safe.');
    }

    /**
     * The EXACT bytes we sent. Not a rebuilt guess: when Crossref rejects a deposit six
     * months later, "what did we actually send?" is the first question, and a regenerated
     * document is not an answer to it.
     */
    public function xml(DoiDeposit $deposit): StreamedResponse
    {
        $deposit->load('journal');

        $this->authorize('depositDois', $deposit->journal);

        abort_if(blank($deposit->payload_path), 404, 'This deposit never got as far as building its XML.');
        abort_unless(Storage::disk('private')->exists($deposit->payload_path), 404);

        return Storage::disk('private')->response(
            $deposit->payload_path,
            "{$deposit->batch_id}.xml",
            [
                'Content-Type' => 'application/xml',
                'Content-Disposition' => 'inline; filename="'.$deposit->batch_id.'.xml"',
            ],
        );
    }

    /** @return array<string, mixed> */
    private function present(DoiDeposit $deposit, Journal $journal): array
    {
        return [
            'id' => $deposit->id,
            'batchId' => $deposit->batch_id,

            // sandbox | production. The single most consequential fact on this screen.
            'endpoint' => $deposit->endpoint,
            'isProduction' => $deposit->endpoint === 'production',

            'status' => $deposit->status->value,
            'statusLabel' => $deposit->status->label(),
            'isRetryable' => $deposit->status->isRetryable(),
            'attempts' => $deposit->attempts,

            'issue' => $deposit->issue === null ? null : [
                'id' => $deposit->issue->id,
                'label' => "Vol {$deposit->issue->volume?->number}, No {$deposit->issue->number}",
            ],

            'submittedAt' => $deposit->submitted_at?->toIso8601String(),
            'completedAt' => $deposit->completed_at?->toIso8601String(),
            'submittedBy' => $deposit->submittedBy?->fullName(),

            // Crossref's ACTUAL words — the only thing that makes a failure diagnosable —
            // with the credentials it echoes back struck out of them first.
            'error' => DepositRedactor::scrub($deposit->error_message, $journal),

            'hasPayload' => filled($deposit->payload_path),

            'items' => $deposit->items->map(fn (DoiDepositItem $item): array => [
                'id' => $item->id,
                'doi' => $item->doi,
                'status' => $item->status->value,
                'statusLabel' => $item->status->label(),
                'title' => $item->article?->title,
                'articleId' => $item->article_id,
                'message' => DepositRedactor::scrub($item->message, $journal),
            ])->values()->all(),
        ];
    }
}
