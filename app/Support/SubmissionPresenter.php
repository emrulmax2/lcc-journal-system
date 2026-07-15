<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ReviewAssignment;
use App\Models\Submission;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ===========================================================================
 *  THE ANONYMITY BOUNDARY. Under single-blind review, a reviewer's identity
 *  must NEVER reach an author — through any endpoint, serialiser, eager-load
 *  or error message. A leak here is a research-integrity incident, not a bug.
 * ===========================================================================
 *
 * This is the ONLY class allowed to read a ReviewAssignment's `reviewer` relation, and it
 * will not emit a name, an affiliation or an avatar unless the caller has proved the viewer
 * holds `review.assign` ON THAT JOURNAL (JournalPolicy::assignReviewers). The caller passes
 * that answer in as $revealReviewers; it is never inferred here.
 *
 * `comments_to_editor` appears NOWHERE in this file, and must not be added to it. The
 * Dashboard shows an author a reviewer's STATUS and RECOMMENDATION — the two facts they
 * are entitled to — and nothing whatsoever about the person.
 *
 * Dashboard.tsx renders reviewer.name / .affiliation / .avatar unconditionally, and it is
 * a SHARED component: the same page serves authors and editors. The fix is therefore here,
 * in the backend, and not in the component. An author receives "Reviewer 1", a stated
 * withholding, and a neutral silhouette — real values in the right shape, so the page has
 * nothing to fall back to and no branch that could be got wrong.
 */
final class SubmissionPresenter
{
    /**
     * A neutral silhouette, inline. Not a remote avatar service: a request to a third-party
     * URL keyed on a real person's identity would leak that identity out of the page even
     * if the page itself never printed it.
     */
    private const ANONYMOUS_AVATAR = 'data:image/svg+xml;charset=utf-8,'
        .'%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2040%2040%22%3E'
        .'%3Crect%20width%3D%2240%22%20height%3D%2240%22%20fill%3D%22%23E2E8F0%22%2F%3E'
        .'%3Ccircle%20cx%3D%2220%22%20cy%3D%2215%22%20r%3D%226%22%20fill%3D%22%2394A3B8%22%2F%3E'
        .'%3Cpath%20d%3D%22M8%2036c0-6.6%205.4-12%2012-12s12%205.4%2012%2012%22%20fill%3D%22%2394A3B8%22%2F%3E'
        .'%3C%2Fsvg%3E';

    /** What goes in the affiliation slot for an author. It states the rule rather than lying. */
    private const WITHHELD = 'Identity withheld — single-blind review';

    /**
     * One row of Dashboard.tsx's `submissions` array.
     *
     * @param  bool  $revealReviewers  TRUE only when the viewer holds `review.assign` on
     *                                 this submission's journal. Defaults to the safe answer.
     * @return array<string, mixed>
     */
    public static function forDashboard(Submission $submission, bool $revealReviewers = false): array
    {
        return [
            // A draft has no reference — references are issued at submission, so that an
            // abandoned draft does not burn a number. The row still needs a stable key.
            'id' => $submission->reference ?? 'DRAFT-'.$submission->id,

            'title' => $submission->title,
            'journal' => $submission->journal?->title ?? '',
            'status' => $submission->status->label(),
            'submitted' => ($submission->submitted_at ?? $submission->created_at)?->toIso8601String() ?? '',
            'updated' => $submission->updated_at?->toIso8601String() ?? '',
            'stage' => $submission->stage->value,
            'correspondingAuthor' => self::correspondingAuthor($submission),
            'type' => $submission->section?->name,
            'reviewers' => self::reviewers($submission, $revealReviewers),
        ];
    }

    /**
     * The reviewers on the CURRENT round.
     *
     * Only the current round, deliberately: the Dashboard's panel has no round selector, so
     * merging rounds would show two "Reviewer 1"s (React keys on the name) and mix a closed
     * round's verdicts in with a live one's.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function reviewers(Submission $submission, bool $reveal): array
    {
        $round = $submission->currentRound();

        if ($round === null) {
            return [];
        }

        return $round->assignments()
            ->with($reveal ? ['reviewer', 'review'] : ['review'])
            ->get()
            ->values()
            ->map(fn (ReviewAssignment $a, int $i): array => self::reviewer($a, $i + 1, $reveal))
            ->all();
    }

    /** @return array<string, mixed> */
    private static function reviewer(ReviewAssignment $assignment, int $position, bool $reveal): array
    {
        $identity = $reveal
            ? [
                'name' => $assignment->reviewer->fullName(),
                'affiliation' => (string) ($assignment->reviewer->affiliation ?? ''),
                'avatar' => self::avatar($assignment->reviewer->avatar_path),
            ]
            : [
                // Stable within the round, so an author can follow "Reviewer 2" from one
                // visit to the next without ever learning who Reviewer 2 is.
                'name' => 'Reviewer '.$position,
                'affiliation' => self::WITHHELD,
                'avatar' => self::ANONYMOUS_AVATAR,
            ];

        return $identity + [
            'status' => $assignment->status->label(),

            // The recommendation IS the author's to see, once the report lands. The
            // comments_to_editor that came with it is NOT, and is not read here at all.
            'recommendation' => $assignment->review?->recommendation->label(),

            'due' => $assignment->due_at->toIso8601String(),
        ];
    }

    private static function avatar(?string $path): string
    {
        if (blank($path)) {
            return self::ANONYMOUS_AVATAR;
        }

        return Str::startsWith($path, ['http://', 'https://', 'data:'])
            ? $path
            : Storage::disk('public')->url($path);
    }

    private static function correspondingAuthor(Submission $submission): string
    {
        $named = $submission->authors->firstWhere('is_corresponding', true)
            ?? $submission->authors->first();

        return $named?->name
            ?? $submission->correspondingAuthor?->fullName()
            ?? '';
    }
}
