<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Journal;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who to reach about a manuscript.
 *
 * The corresponding-author address is deliberately not just `correspondingAuthor->email`: a
 * manuscript may be submitted by a GUEST with no account, and then the only address is the
 * SubmissionAuthor flagged is_corresponding. Decision letters and receipts must reach them
 * either way.
 *
 * `editorsOf` is the journal's editorial staff — everyone who may view the whole queue. It is
 * both the discussion-participant candidate list and (in Phase 2) the set notified when a
 * reviewer declines or a round completes.
 */
final class EditorialRecipients
{
    public static function correspondingAuthorEmail(Submission $submission): ?string
    {
        $author = $submission->authors->firstWhere('is_corresponding', true)
            ?? $submission->authors->first();

        return $author?->email
            ?? $submission->correspondingAuthor?->email;
    }

    /**
     * Editorial staff on the journal — the users who may view all submissions. Asked of the
     * policy per user (a journal has few staff), so it tracks exactly what the cockpit admits.
     *
     * @return Collection<int, User>
     */
    public static function editorsOf(Journal $journal): Collection
    {
        return $journal->users()
            ->get()
            ->unique('id')
            ->filter(fn (User $user): bool => (bool) $user->is_active && $user->can('viewAllSubmissions', $journal))
            ->values();
    }
}
