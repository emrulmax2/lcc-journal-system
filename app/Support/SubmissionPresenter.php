<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\EditorialDecision;
use App\Models\ReviewAssignment;
use App\Models\ReviewRound;
use App\Models\Submission;
use App\Models\SubmissionAuthor;
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

            // The numeric id, for author actions that POST (revision upload). NOT sensitive —
            // it is the author's own manuscript.
            'submissionId' => $submission->id,

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
     * The EDITOR's view of a manuscript — every round, every report, the confidential
     * editor-only comments, and reviewer identities WHEN the viewer may see them.
     *
     * This is the counterpart to forDashboard, and the differences are deliberate:
     *
     *   - ALL rounds, not just the current one. An editor deciding a round-two manuscript
     *     needs round one's reports in front of them; the detail screen has the room the
     *     Dashboard's panel does not.
     *
     *   - `commentsToEditor` IS emitted here. It is confidential FROM THE AUTHOR, not from
     *     the editor — and this method only ever feeds a screen already gated on
     *     `viewAllSubmissions`. It must still never reach forDashboard, which serves authors.
     *
     *   - Identities are gated on $revealReviewers exactly as everywhere else. An editor who
     *     may view submissions but not assign reviewers (a possible, if unusual, permission
     *     split) sees "Reviewer 1", not a name.
     *
     * @return array<string, mixed>
     */
    public static function forEditor(Submission $submission, bool $revealReviewers): array
    {
        return [
            'id' => $submission->id,
            'reference' => $submission->reference,
            'title' => $submission->title,
            'abstract' => $submission->abstract,
            'status' => $submission->status->value,
            'statusLabel' => $submission->status->label(),
            'stage' => $submission->stage->value,
            'stageLabel' => $submission->stage->label(),
            'keywords' => $submission->keywords ?? [],
            'section' => $submission->section?->name,
            'submittedAt' => $submission->submitted_at?->toIso8601String(),

            'authors' => $submission->authors->map(fn (SubmissionAuthor $author): array => [
                'name' => $author->name,
                'email' => $author->email,
                'affiliation' => $author->affiliation,
                'orcid' => $author->orcid,
                'isCorresponding' => (bool) $author->is_corresponding,
            ])->values()->all(),

            'rounds' => $submission->reviewRounds
                ->map(fn (ReviewRound $round): array => self::editorRound($round, $revealReviewers))
                ->values()
                ->all(),

            'decisions' => $submission->decisions->map(fn (EditorialDecision $decision): array => [
                'decision' => $decision->decision->value,
                'decisionLabel' => $decision->decision->label(),
                'body' => $decision->body,
                'editor' => $decision->editor?->fullName(),
                'roundNumber' => $decision->round?->round_number,
                'decidedAt' => $decision->decided_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * One review round for the editor screen — every assignment, its report, and (gated)
     * the reviewer's identity. Assignments are ordered by id, the same order the anonymised
     * "Reviewer 1/2/3" labels use, so a withheld label is stable across visits.
     *
     * @return array<string, mixed>
     */
    private static function editorRound(ReviewRound $round, bool $reveal): array
    {
        return [
            'id' => $round->id,
            'roundNumber' => $round->round_number,
            'openedAt' => $round->opened_at?->toIso8601String(),
            'closedAt' => $round->closed_at?->toIso8601String(),
            'isOpen' => $round->isOpen(),
            'allReportsIn' => $round->allReportsIn(),
            'assignments' => $round->assignments
                ->values()
                ->map(fn (ReviewAssignment $a, int $i): array => self::editorAssignment($a, $i + 1, $reveal))
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private static function editorAssignment(ReviewAssignment $assignment, int $position, bool $reveal): array
    {
        $identity = $reveal
            ? [
                'reviewerName' => $assignment->reviewer->fullName(),
                'reviewerAffiliation' => (string) ($assignment->reviewer->affiliation ?? ''),
            ]
            : [
                'reviewerName' => 'Reviewer '.$position,
                'reviewerAffiliation' => self::WITHHELD,
            ];

        $review = $assignment->review;

        return $identity + [
            'id' => $assignment->id,
            'status' => $assignment->status->value,
            'statusLabel' => $assignment->status->label(),
            'invitedAt' => $assignment->invited_at?->toIso8601String(),
            'dueAt' => $assignment->due_at->toIso8601String(),
            'respondedAt' => $assignment->responded_at?->toIso8601String(),
            'completedAt' => $assignment->completed_at?->toIso8601String(),

            // The report, if it has landed. `commentsToEditor` is read directly off the model
            // — $hidden guards ->toArray()/->toJson(), not property access, and this array is
            // built for the editor screen on purpose.
            'report' => $review === null ? null : [
                'recommendation' => $review->recommendation->value,
                'recommendationLabel' => $review->recommendation->label(),
                'commentsToAuthor' => $review->comments_to_author,
                'commentsToEditor' => $review->comments_to_editor,
                'submittedAt' => $review->submitted_at?->toIso8601String(),
            ],
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
