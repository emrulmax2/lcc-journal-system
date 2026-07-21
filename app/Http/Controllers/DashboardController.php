<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ReviewerStatus;
use App\Enums\SubmissionStage;
use App\Enums\SubmissionStatus;
use App\Models\Journal;
use App\Models\ReviewAssignment;
use App\Models\Submission;
use App\Models\User;
use App\Support\AdminChrome;
use App\Support\EditorialMetrics;
use App\Support\SubmissionPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The editorial office — one page serving three roles at once: author, reviewer, editor.
 *
 * EVERY number here is computed from real rows. The prototype's KPI tiles, decision-time
 * series and checklist were module constants in lib/data.ts; a dashboard that reports
 * numbers nobody measured is worse than one that reports nothing, because it is believed.
 * Where there is nothing to measure, the tile is omitted rather than shown as 0 — see the
 * median below.
 *
 * ANONYMITY: the submissions list is serialised by SubmissionPresenter, and the reveal flag
 * is decided PER JOURNAL, per submission, by SubmissionPolicy::seeReviewerIdentities. An
 * editor of Journal A viewing their own manuscript in Journal B sees "Reviewer 1" on it,
 * because on Journal B they are just an author.
 */
final class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        // Journals where this person is an editor: the ones whose full queue they may see.
        $editorJournalIds = $this->journalsWhereUserCan($user, 'viewAllSubmissions');

        $submissions = Submission::query()
            ->with(['journal', 'section', 'authors', 'decisions'])
            ->where(function ($query) use ($user, $editorJournalIds) {
                $query
                    // As an author: everything they own, drafts included.
                    ->where('corresponding_author_id', $user->id)
                    // As an editor: everything in their journals EXCEPT drafts. "Nothing
                    // goes to an editor until the final step" is a promise the wizard makes
                    // to the author, and this is where it is kept.
                    ->orWhere(fn ($editorial) => $editorial
                        ->whereIn('journal_id', $editorJournalIds)
                        ->visibleToEditors()
                    );
            })
            ->orderByDesc('updated_at')
            ->get();

        $reveal = $submissions
            ->filter(fn (Submission $s): bool => $user->can('seeReviewerIdentities', $s))
            ->pluck('id')
            ->all();

        return Inertia::render('Dashboard', [
            'kpis' => $this->kpis($submissions, $editorJournalIds),

            'submissions' => $submissions
                ->map(fn (Submission $s): array => SubmissionPresenter::forDashboard(
                    $s,
                    in_array($s->id, $reveal, true),
                ))
                ->values(),

            'reviewQueue' => $this->reviewQueue($user),
            'decisionTime' => $this->decisionTime($submissions),
            'checklist' => $this->checklist($focus = $this->focusSubmission($submissions, $editorJournalIds)),

            // The manuscript the checklist describes, so the card can link straight to the
            // editor cockpit where an outstanding item (a missing report, a pending decision)
            // can actually be acted on. Null when this person has no manuscript with them.
            'checklistFocus' => $focus === null ? null : [
                'id' => $focus->id,
                'reference' => $focus->reference,
            ],

            /*
             * THE WAY OUT OF THIS ROOM.
             *
             * There are two dashboards and this is the one login used to send EVERYBODY to.
             * It had no link to the other. An editor — or a site admin, for whom this page is
             * legitimately empty — landed here, saw four zeroes and a New submission button,
             * and had no route to issues, articles, publication or DOIs short of typing
             * /admin. LandingPage now sends them to the right room in the first place; this
             * is for the ones who arrive here anyway, from a bookmark or the nav.
             *
             * Same question Admin\DashboardController asks before it 403s, so the button is
             * never a link to a refusal.
             */
            'canAccessAdmin' => AdminChrome::editorialJournals($user)->isNotEmpty(),

            'meta' => [
                'title' => 'Editorial office — '.config('app.name'),
                'description' => 'Your submissions, reviews and editorial decisions.',
            ],
        ]);
    }

    /**
     * The four tiles, in the order Dashboard.tsx's icon map expects — the labels ARE the
     * contract there, so do not reword them.
     *
     * A tile is OMITTED, not zeroed, when it has nothing real to say: "0 days to decision"
     * on a journal that has never decided anything is a false statistic, whereas "0 accepted
     * this year" is a true one. That is the whole distinction.
     *
     * @param  Collection<int, Submission>  $submissions
     * @param  array<int, int>  $editorJournalIds
     * @return array<int, array<string, mixed>>
     */
    private function kpis(Collection $submissions, array $editorJournalIds): array
    {
        $kpis = [[
            'label' => 'Active submissions',
            'value' => $submissions->filter(fn (Submission $s): bool => $s->status->isActive())->count(),
            'suffix' => '',
            'tone' => 'brand',
            'icon' => 'inbox',
        ]];

        if ($editorJournalIds !== []) {
            $kpis[] = [
                'label' => 'Awaiting your decision',
                'value' => $this->awaitingDecision($submissions, $editorJournalIds),
                'suffix' => '',
                'tone' => 'gold',
                'icon' => 'gavel',
            ];
        }

        $median = EditorialMetrics::median($this->daysToFirstDecision($submissions));

        if ($median !== null) {
            $kpis[] = [
                'label' => 'Median days to decision',
                'value' => $median,
                'suffix' => '',
                'tone' => 'brand',
                'icon' => 'clock',
            ];
        }

        $kpis[] = [
            'label' => 'Accepted this year',
            'value' => $submissions
                ->filter(fn (Submission $s): bool => $s->status === SubmissionStatus::Accepted
                    && $s->decisions->contains(fn ($d): bool => $d->decided_at->year === now()->year))
                ->count(),
            'suffix' => '',
            'tone' => 'gold',
            'icon' => 'file-check',
        ];

        return $kpis;
    }

    /**
     * Manuscripts that have reached the Decision stage and are still waiting on a human —
     * i.e. every report is in and nobody has decided. A manuscript in Revisions Requested is
     * NOT waiting on the editor; it is waiting on the author, and counting it here would
     * make the queue look permanently full.
     *
     * @param  Collection<int, Submission>  $submissions
     * @param  array<int, int>  $editorJournalIds
     */
    private function awaitingDecision(Collection $submissions, array $editorJournalIds): int
    {
        return $submissions
            ->filter(fn (Submission $s): bool => in_array($s->journal_id, $editorJournalIds, true)
                && $s->stage === SubmissionStage::Decision
                && in_array($s->status, [SubmissionStatus::Submitted, SubmissionStatus::UnderReview], true))
            ->count();
    }

    /**
     * "Reviews I owe" — invitations this user has not yet answered or reported on.
     *
     * The id shown is the manuscript's REFERENCE, because that is what a reviewer is asked
     * about in an email and what they quote back. It is never a reviewer's own identifier,
     * and the queue exposes nothing about the other reviewers on the same paper.
     *
     * @return array<int, array<string, mixed>>
     */
    private function reviewQueue(User $user): array
    {
        return ReviewAssignment::query()
            ->outstandingFor($user)
            ->with(['round.submission.journal'])
            ->get()
            ->sortBy('due_at')
            ->map(fn (ReviewAssignment $a): array => [
                'id' => $a->round->submission->reference ?? 'MS-'.$a->round->submission_id,

                /**
                 * The assignment's own id — which the queue did NOT send.
                 *
                 * Without it the Decline and Write-report buttons had nothing to act on,
                 * and they were shipped inert: two controls a reviewer is explicitly asked
                 * to use, that did nothing when clicked. The routes existed the whole time.
                 *
                 * Safe to expose: ReviewAssignmentPolicy already refuses every assignment
                 * that is not this user's own, so knowing an id grants nothing.
                 */
                'assignmentId' => $a->id,
                'status' => $a->status->label(),
                'accepted' => $a->status === ReviewerStatus::Accepted,

                'title' => $a->round->submission->title,
                'journal' => $a->round->submission->journal?->title ?? '',
                'due' => $a->due_at->toIso8601String(),
                'round' => $a->round->round_number,
            ])
            ->values()
            ->all();
    }

    /**
     * Median days to FIRST decision, by month, oldest first — the line the chart draws.
     *
     * Months with no decisions are absent rather than plotted as zero: a month in which
     * nothing was decided is not a month in which everything was decided instantly.
     *
     * @param  Collection<int, Submission>  $submissions
     * @return array<int, array{month: string, days: int}>
     */
    private function decisionTime(Collection $submissions): array
    {
        return $submissions
            ->filter(fn (Submission $s): bool => $s->submitted_at !== null && $s->decisions->isNotEmpty())
            ->map(function (Submission $s): array {
                $first = $s->decisions->sortBy('decided_at')->first();

                return [
                    'key' => $first->decided_at->format('Y-m'),
                    'month' => $first->decided_at->format('M'),
                    'days' => (int) $s->submitted_at->startOfDay()->diffInDays($first->decided_at->startOfDay()),
                ];
            })
            ->groupBy('key')
            ->sortKeys()
            ->take(-6)   // the last six months that actually have decisions in them
            ->map(fn (Collection $month): array => [
                'month' => $month->first()['month'],
                'days' => EditorialMetrics::median($month->pluck('days')) ?? 0,
            ])
            ->values()
            ->all();
    }

    /**
     * The ONE manuscript currently with this editor — the oldest active one in their
     * journals. Null for someone who is not an editor, or who has an empty queue.
     *
     * @param  Collection<int, Submission>  $submissions
     * @param  array<int, int>  $editorJournalIds
     */
    private function focusSubmission(Collection $submissions, array $editorJournalIds): ?Submission
    {
        return $submissions
            ->filter(fn (Submission $s): bool => in_array($s->journal_id, $editorJournalIds, true)
                && $s->status->isActive())
            ->sortBy('submitted_at')
            ->first();
    }

    /**
     * The checklist for the focus manuscript. Empty when there is none — exactly what the
     * component's "no manuscript is currently with you" copy describes.
     *
     * Every item is derived from a row. A checklist that ticks itself off on a schedule is
     * a to-do list; this one is a status report.
     *
     * @return array<int, array{label: string, done: bool}>
     */
    private function checklist(?Submission $focus): array
    {
        if ($focus === null) {
            return [];
        }

        $round = $focus->currentRound();

        return [
            [
                'label' => 'Manuscript received ('.($focus->reference ?? '—').')',
                'done' => $focus->submitted_at !== null,
            ],
            [
                'label' => 'Editor check: scope, ethics and competing interests',
                'done' => $focus->ethics_declared
                    && $focus->conflicts_declared
                    && $focus->stage->value >= SubmissionStage::PeerReview->value,
            ],
            [
                'label' => 'Reviewers invited',
                'done' => $round !== null && $round->assignments()->exists(),
            ],
            [
                'label' => 'All reviewer reports received',
                'done' => $round !== null && $round->allReportsIn(),
            ],
            [
                'label' => 'Decision recorded and sent to the author',
                'done' => $focus->decisions->isNotEmpty(),
            ],
        ];
    }

    /**
     * @param  Collection<int, Submission>  $submissions
     * @return array<int, int>
     */
    private function daysToFirstDecision(Collection $submissions): array
    {
        return $submissions
            ->filter(fn (Submission $s): bool => $s->submitted_at !== null && $s->decisions->isNotEmpty())
            ->map(fn (Submission $s): int => (int) $s->submitted_at
                ->startOfDay()
                ->diffInDays($s->decisions->sortBy('decided_at')->first()->decided_at->startOfDay()))
            ->values()
            ->all();
    }

    /**
     * Ask the POLICY, journal by journal. Roles are per-journal (Spatie teams), so there is
     * no such thing as "is this person an editor" in the abstract — only "is this person an
     * editor of THIS journal". Going through Gate also means site admins are handled by the
     * one global bypass in AppServiceProvider rather than by a second rule here.
     *
     * @return array<int, int>
     */
    private function journalsWhereUserCan(User $user, string $ability): array
    {
        return Journal::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (Journal $journal): bool => $user->can($ability, $journal))
            ->pluck('id')
            ->all();
    }
}
