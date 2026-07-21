<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Journal;
use App\Models\ReviewAssignment;
use App\Models\ReviewRound;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The reviewers an editor may invite ON THIS JOURNAL.
 *
 * "Holds the reviewer role on this journal" cannot be asked through $user->roles with teams
 * enabled — that relation is scoped to the CURRENT team id, and here we want a specific one.
 * So the model_has_roles pivot is queried directly, filtered to this journal's id (the team
 * key IS journal_id, see config/permission.php), exactly as GlobalRoles does from the other
 * direction. This is the pool the invite form offers and the set AssignReviewerAction's
 * validation is scoped to.
 *
 * The corresponding author is NEVER in the pool: an author cannot review their own paper,
 * and AssignReviewerAction refuses it at the database — but offering the name and then
 * refusing it is a worse experience than never offering it.
 */
final class ReviewerPool
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forJournal(Journal $journal, ?ReviewRound $round = null): array
    {
        $reviewers = User::query()
            ->whereIn('id', self::reviewerIdsOn($journal))
            ->where('is_active', true)
            ->with('reviewerProfile')
            ->orderBy('name')
            ->get();

        $inRound = $round === null
            ? []
            : $round->assignments()->pluck('reviewer_id')->all();

        return $reviewers
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->fullName(),
                'affiliation' => $user->affiliation,
                'expertise' => $user->reviewerProfile?->expertise ?? [],
                'available' => (bool) ($user->reviewerProfile?->available ?? true),
                'maxConcurrent' => $user->reviewerProfile?->max_concurrent_reviews,

                // Reviews this person owes ANYONE, across every journal — capacity is a
                // property of the person, not of one journal's queue.
                'currentLoad' => ReviewAssignment::query()->outstandingFor($user)->count(),

                // Already invited in this round: the invite form disables them rather than
                // letting the editor create a duplicate the unique index would reject.
                'alreadyInRound' => in_array($user->id, $inRound, true),
            ])
            ->values()
            ->all();
    }

    /**
     * The ids of users holding the `reviewer` role scoped to this journal.
     *
     * @return array<int, int>
     */
    public static function reviewerIdsOn(Journal $journal): array
    {
        $tables = config('permission.table_names');
        $columns = config('permission.column_names');

        return DB::table($tables['model_has_roles'])
            ->join($tables['roles'], "{$tables['roles']}.id", '=', "{$tables['model_has_roles']}.role_id")
            ->where("{$tables['model_has_roles']}.model_type", (new User)->getMorphClass())
            ->where("{$tables['model_has_roles']}.{$columns['team_foreign_key']}", $journal->id)
            ->where("{$tables['roles']}.name", 'reviewer')
            ->pluck("{$tables['model_has_roles']}.{$columns['model_morph_key']}")
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
