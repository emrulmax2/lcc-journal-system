<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ArticleStatus;
use App\Enums\DecisionType;
use App\Enums\SubmissionStatus;
use App\Models\Journal;
use App\Models\JournalMetric;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Computes the journal metrics that ARE ours to compute.
 *
 * The distinction this command exists to enforce:
 *
 *   EXTERNAL — impact_factor, cite_score. Issued by Clarivate (JCR) and Scopus. They are
 *              entered by hand and this command NEVER touches them. Computing our own
 *              "impact factor" and displaying it under that name would be a fabrication;
 *              it is a trademarked, externally-audited figure.
 *
 *   OURS     — acceptance_rate, median_days_to_decision, article_count, editor_count.
 *              These come from our own submission and decision records. They are computed
 *              on a schedule precisely so that nobody can type a flattering number into a
 *              field that authors will read as a fact when choosing where to submit.
 *
 * A metric with no data behind it is left NULL, never zeroed. "0% acceptance rate" and
 * "0 days to decision" are not honest descriptions of a journal that has not yet decided
 * anything — they are wrong, and they are wrong in a direction that flatters us.
 */
class ComputeJournalMetricsCommand extends Command
{
    protected $signature = 'journal:compute-metrics {--journal= : Only this journal slug}';

    protected $description = 'Recompute the journal metrics derived from our own data';

    public function handle(): int
    {
        $journals = Journal::query()
            ->when($this->option('journal'), fn ($q, $slug) => $q->where('slug', $slug))
            ->get();

        foreach ($journals as $journal) {
            $metric = JournalMetric::firstOrNew(['journal_id' => $journal->id]);

            $metric->article_count = $journal->articles()
                ->where('status', ArticleStatus::Published)
                ->count();

            $metric->editor_count = $this->editorCount($journal);
            $metric->acceptance_rate = $this->acceptanceRate($journal);
            $metric->median_days_to_decision = $this->medianDaysToDecision($journal);
            $metric->computed_at = now();

            // NOTE: impact_factor and cite_score are deliberately untouched.
            $metric->save();

            $this->line(sprintf(
                '  %-28s articles=%-5d editors=%-3d acceptance=%-6s median_days=%s',
                $journal->slug,
                $metric->article_count,
                $metric->editor_count,
                $metric->acceptance_rate === null ? '—' : $metric->acceptance_rate.'%',
                $metric->median_days_to_decision ?? '—',
            ));
        }

        $this->info('Done. impact_factor and cite_score were not touched — they are external (JCR/Scopus).');

        return self::SUCCESS;
    }

    private function editorCount(Journal $journal): int
    {
        return (int) DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.journal_id', $journal->id)
            ->whereIn('roles.name', ['journal-editor', 'section-editor'])
            ->distinct()
            ->count('model_has_roles.model_id');
    }

    /**
     * Accepted / (accepted + rejected). Submissions still under review are EXCLUDED —
     * counting them as "not yet accepted" would depress the rate; counting them as
     * accepted would inflate it. Only decided submissions can answer the question.
     */
    private function acceptanceRate(Journal $journal): ?int
    {
        $decided = $journal->submissions()
            ->whereIn('status', [SubmissionStatus::Accepted, SubmissionStatus::Rejected])
            ->count();

        if ($decided === 0) {
            return null;   // no decisions yet — NULL, not 0
        }

        $accepted = $journal->submissions()
            ->where('status', SubmissionStatus::Accepted)
            ->count();

        return (int) round($accepted / $decided * 100);
    }

    /**
     * Median, not mean. One manuscript that sat with a reviewer for a year would drag a
     * mean well away from the number that describes a typical author's experience — which
     * is the number an author is actually trying to find out.
     *
     * MySQL 5.7 has no window functions, so this is computed in PHP.
     */
    private function medianDaysToDecision(Journal $journal): ?int
    {
        $days = DB::table('editorial_decisions')
            ->join('submissions', 'submissions.id', '=', 'editorial_decisions.submission_id')
            ->where('submissions.journal_id', $journal->id)
            ->whereNotNull('submissions.submitted_at')
            ->whereIn('editorial_decisions.decision', [
                DecisionType::Accept->value,
                DecisionType::Reject->value,
                DecisionType::MinorRevision->value,
                DecisionType::MajorRevision->value,
            ])
            // FIRST decision only. Time-to-first-decision is what authors care about;
            // averaging in every revision round measures something else entirely.
            ->selectRaw('submissions.id, MIN(DATEDIFF(editorial_decisions.decided_at, submissions.submitted_at)) as days')
            ->groupBy('submissions.id')
            ->pluck('days')
            ->filter(fn ($d) => $d !== null && $d >= 0)
            ->sort()
            ->values();

        if ($days->isEmpty()) {
            return null;
        }

        $count = $days->count();
        $middle = (int) floor($count / 2);

        return (int) round(
            $count % 2 === 0
                ? ($days[$middle - 1] + $days[$middle]) / 2
                : $days[$middle]
        );
    }
}
