<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ReviewerStatus;
use App\Enums\SubmissionStage;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Journal;
use App\Models\Submission;
use App\Models\SubmissionDiscussion;
use App\Models\SubmissionEvent;
use App\Models\SubmissionFile;
use App\Support\AdminChrome;
use App\Support\EditorialRecipients;
use App\Support\ReviewerPool;
use App\Support\SubmissionPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The editorial cockpit — the queue an editor triages from, and the detail screen where they
 * read a manuscript, its reviewer reports and its audit trail.
 *
 * This controller is READ-ONLY. It never mutates a submission: inviting a reviewer and
 * recording a decision go to the EXISTING audited endpoints (ReviewController@invite,
 * DecisionController@store), which already open rounds, enforce the anonymity boundary and
 * write the event trail. The job here is to render what those endpoints act on.
 *
 * Everything below `show` is EDITOR-ONLY. It surfaces confidential reviewer comments and, to
 * those permitted, reviewer identities — so it gates on `viewAllSubmissions`, not on the
 * author-and-editor `view`. An author reads their own manuscript on their Dashboard, which
 * goes through SubmissionPresenter::forDashboard and never sees any of this.
 */
final class SubmissionController extends Controller
{
    public function index(Request $request, Journal $journal): Response
    {
        $this->authorize('viewAllSubmissions', $journal);

        $status = self::enumFilter($request->query('status'), SubmissionStatus::class);
        $stage = self::enumFilter($request->query('stage'), SubmissionStage::class);
        $overdueOnly = $request->boolean('overdue');

        $submissions = $journal->submissions()
            ->visibleToEditors()
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->when($stage !== null, fn ($q) => $q->where('stage', $stage))
            ->with([
                'authors',
                'section',
                'reviewRounds.assignments',
                'decisions',
            ])
            ->orderByDesc('updated_at')
            ->get();

        $now = now();

        $rows = $submissions
            ->map(fn (Submission $submission): array => $this->row($submission, $now))
            ->when($overdueOnly, fn ($rows) => $rows->filter(fn (array $r): bool => $r['overdueCount'] > 0))
            ->values()
            ->all();

        return Inertia::render('Admin/Submissions', array_merge(
            AdminChrome::for($request->user(), $journal),
            [
                'submissions' => $rows,
                'filters' => [
                    'status' => $status?->value,
                    'stage' => $stage?->value,
                    'overdue' => $overdueOnly,
                ],
                'statusOptions' => self::options(SubmissionStatus::cases()),
                'stageOptions' => self::options(SubmissionStage::cases()),
                'meta' => [
                    'title' => 'Submissions — '.$journal->title,
                    'description' => 'Manuscripts in peer review, and where each one is.',
                ],
            ],
        ));
    }

    public function show(Request $request, Submission $submission): Response
    {
        // EDITOR-ONLY. `view` would also admit the author; this screen carries confidential
        // reviewer comments, so it asks the editor question.
        $this->authorize('viewAllSubmissions', $submission->journal);

        $reveal = $request->user()->can('seeReviewerIdentities', $submission);

        $submission->load([
            'journal',
            'section',
            'authors',
            'files.uploader',
            'reviewRounds.assignments' => fn ($q) => $q->with($reveal ? ['reviewer', 'review'] : ['review']),
            'decisions.editor',
            'decisions.round',
            'events.user',
            'discussions.messages.author',
            'discussions.participants',
        ]);

        $openRound = $submission->openRound();

        return Inertia::render('Admin/SubmissionDetail', array_merge(
            AdminChrome::for($request->user(), $submission->journal),
            [
                'submission' => SubmissionPresenter::forEditor($submission, $reveal),
                'files' => $submission->files
                    ->sortBy([['version', 'asc'], ['id', 'asc']])
                    ->map(fn (SubmissionFile $file): array => [
                        'id' => $file->id,
                        'version' => $file->version,
                        'type' => $file->type->value,
                        'typeLabel' => $file->type->label(),
                        'originalName' => $file->original_name,
                        'sizeBytes' => $file->size_bytes,
                        'uploadedAt' => $file->created_at?->toIso8601String(),
                        'downloadHref' => route('admin.submissions.files.download', [$submission->id, $file->id]),
                    ])
                    ->values()
                    ->all(),
                'timeline' => $submission->events
                    ->map(fn (SubmissionEvent $event): array => [
                        'event' => $event->event,
                        'label' => self::eventLabel($event->event),
                        'actor' => $event->user?->fullName(),
                        'at' => $event->created_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),

                // Only offered when the viewer may actually invite — the form POSTs to
                // ReviewController@invite, which re-authorises anyway. Whether to RENDER the
                // form is read from AdminChrome's `can` (assignReviewers / recordDecision),
                // which is journal-scoped and identical to the submission-scoped answer.
                'reviewerPool' => $request->user()->can('assignReviewers', $submission)
                    ? ReviewerPool::forJournal($submission->journal, $openRound)
                    : [],

                'decisionState' => [
                    'decided' => $submission->status->isDecided(),
                    'hasOpenRound' => $openRound !== null,
                    'allReportsIn' => $openRound?->allReportsIn() ?? false,
                ],

                'discussions' => $submission->discussions
                    ->map(fn (SubmissionDiscussion $d): array => [
                        'id' => $d->id,
                        'subject' => $d->subject,
                        'stage' => $d->stage?->value,
                        'stageLabel' => $d->stage?->label(),
                        'participants' => $d->participants->map(fn ($u): string => $u->fullName())->values()->all(),
                        'messages' => $d->messages->map(fn ($m): array => [
                            'id' => $m->id,
                            'author' => $m->author?->fullName(),
                            'body' => $m->body,
                            'at' => $m->created_at?->toIso8601String(),
                        ])->values()->all(),
                    ])
                    ->values()
                    ->all(),

                // Editorial staff who can be added to a new thread — never the author, never a
                // reviewer. Excludes the current user (always a participant of what they start).
                'participantCandidates' => EditorialRecipients::editorsOf($submission->journal)
                    ->reject(fn ($u): bool => $u->id === $request->user()->id)
                    ->map(fn ($u): array => ['id' => $u->id, 'name' => $u->fullName()])
                    ->values()
                    ->all(),

                'meta' => [
                    'title' => ($submission->reference ?? 'Submission').' — '.$submission->journal->title,
                    'description' => 'The manuscript, its reviewers and every decision on it.',
                ],
            ],
        ));
    }

    /**
     * Stream a submission file off the PRIVATE disk. There is no public URL for these — an
     * unpublished manuscript under review must not be guessable — so an editor reads it
     * through here, re-authorised per request.
     */
    public function download(Submission $submission, SubmissionFile $file): StreamedResponse
    {
        $this->authorize('viewAllSubmissions', $submission->journal);

        // The route binds submission and file independently; a file from another submission
        // must not be reachable by pairing its id with a submission the viewer may see.
        abort_unless($file->submission_id === $submission->id, 404);
        abort_unless(Storage::disk('private')->exists($file->path), 404);

        // attachment, not inline: an editor is fetching the manuscript to read locally, not
        // previewing a published galley. Streamed off the private disk, same as the public
        // PDF route — these files never get a public URL.
        return Storage::disk('private')->response(
            $file->path,
            $file->original_name,
            ['Content-Disposition' => 'attachment; filename="'.addslashes($file->original_name).'"'],
        );
    }

    /**
     * One queue row. The review summary is derived from the LATEST round only — the queue is
     * a triage list, not the full history, which the detail screen carries.
     *
     * @return array<string, mixed>
     */
    private function row(Submission $submission, Carbon $now): array
    {
        $round = $submission->reviewRounds->sortByDesc('round_number')->first();
        $assignments = $round?->assignments ?? collect();

        $overdue = $assignments
            ->filter(fn ($a): bool => $a->status->isOutstanding() && $a->due_at !== null && $a->due_at->lt($now))
            ->count();

        return [
            'id' => $submission->id,
            'reference' => $submission->reference,
            'title' => $submission->title,
            'status' => $submission->status->value,
            'statusLabel' => $submission->status->label(),
            'stage' => $submission->stage->value,
            'stageLabel' => $submission->stage->label(),
            'correspondingAuthor' => $this->correspondingAuthor($submission),
            'section' => $submission->section?->name,
            'submittedAt' => $submission->submitted_at?->toIso8601String(),
            'updatedAt' => $submission->updated_at?->toIso8601String(),
            'roundNumber' => $round?->round_number,
            'reviewerCount' => $assignments->count(),
            'reportsIn' => $assignments->where('status', ReviewerStatus::ReportSubmitted)->count(),
            'overdueCount' => $overdue,
            'decided' => $submission->status->isDecided(),
        ];
    }

    private function correspondingAuthor(Submission $submission): string
    {
        $named = $submission->authors->firstWhere('is_corresponding', true)
            ?? $submission->authors->first();

        return $named?->name ?? '';
    }

    /**
     * @param  class-string  $enum
     */
    private static function enumFilter(mixed $value, string $enum): mixed
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        return $enum::tryFrom(is_string($value) ? $value : (string) $value);
    }

    /**
     * @param  array<int, SubmissionStatus|SubmissionStage>  $cases
     * @return array<int, array{value: int|string, label: string}>
     */
    private static function options(array $cases): array
    {
        return array_map(fn ($case): array => [
            'value' => $case->value,
            'label' => $case->label(),
        ], $cases);
    }

    private static function eventLabel(string $event): string
    {
        return match ($event) {
            'draft.created' => 'Draft created',
            'submission.submitted' => 'Submitted',
            'round.opened' => 'Review round opened',
            'reviewer.assigned' => 'Reviewer invited',
            'reviewer.accepted' => 'Reviewer accepted',
            'reviewer.declined' => 'Reviewer declined',
            'review.submitted' => 'Report submitted',
            'decision.recorded' => 'Decision recorded',
            'submission.converted' => 'Converted to article',
            default => ucfirst(str_replace(['.', '_'], ' ', $event)),
        };
    }
}
