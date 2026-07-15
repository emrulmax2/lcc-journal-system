<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\SubmissionStatus;
use App\Models\Journal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Journal statistics, COMPUTED FROM REAL DATA.
 *
 * Nothing on the Submit wizard or the Dashboard may be a literal. An author chooses a
 * journal partly on "51 days to decision"; a number nobody measured is a claim we cannot
 * stand behind, and the prototype's hardcoded ones were exactly that.
 *
 * Returns NULL rather than 0 when there is nothing to measure. A launch journal has no
 * acceptance rate, and printing "0% acceptance" on the page an author chooses with is
 * worse than printing nothing — the frontend already knows how to render a NULL.
 *
 * No window functions and no CTEs: the dev server is MySQL 5.7.
 */
final class EditorialMetrics
{
    /** @param  iterable<int|float>  $values */
    public static function median(iterable $values): ?int
    {
        $sorted = collect($values)->filter(fn ($v): bool => $v !== null)->sort()->values();

        if ($sorted->isEmpty()) {
            return null;
        }

        $middle = intdiv($sorted->count(), 2);

        // Even count: the mean of the two middle values, rounded — a median of 40.5 days
        // reported as "40 days" would be a systematic under-statement.
        return $sorted->count() % 2 === 1
            ? (int) round((float) $sorted[$middle])
            : (int) round(((float) $sorted[$middle - 1] + (float) $sorted[$middle]) / 2);
    }

    /**
     * Days from submission to FIRST decision, across every decided manuscript in a journal.
     *
     * The first decision is what the metric means: a paper that went round twice did not
     * take 140 days to a first decision — it took 40, and was then revised.
     */
    public static function medianDaysToFirstDecision(Journal $journal): ?int
    {
        $rows = DB::table('editorial_decisions as d')
            ->join('submissions as s', 's.id', '=', 'd.submission_id')
            ->where('s.journal_id', $journal->id)
            ->whereNotNull('s.submitted_at')
            ->groupBy('d.submission_id')
            ->selectRaw('MIN(d.decided_at) as first_decided, MIN(s.submitted_at) as submitted_at')
            ->get();

        return self::median($rows->map(fn (object $row): int => (int) CarbonImmutable::parse($row->submitted_at)
            ->startOfDay()
            ->diffInDays(CarbonImmutable::parse($row->first_decided)->startOfDay())
        ));
    }

    /** Accepted as a percentage of everything decided. NULL until something has been decided. */
    public static function acceptanceRate(Journal $journal): ?int
    {
        $decided = $journal->submissions()
            ->whereIn('status', [SubmissionStatus::Accepted, SubmissionStatus::Rejected])
            ->count();

        if ($decided === 0) {
            return null;
        }

        $accepted = $journal->submissions()
            ->where('status', SubmissionStatus::Accepted)
            ->count();

        return (int) round($accepted / $decided * 100);
    }
}
