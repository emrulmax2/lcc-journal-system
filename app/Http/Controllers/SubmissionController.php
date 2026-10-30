<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AuthorRevisionAction;
use App\Actions\SubmitManuscriptAction;
use App\Enums\SubmissionStatus;
use App\Http\Resources\IssueCardResource;
use App\Mail\SubmissionReceivedMail;
use App\Models\Issue;
use App\Models\Journal;
use App\Models\JournalSection;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\RevisionSubmittedNotification;
use App\Rules\SensibleText;
use App\Support\EditorialMetrics;
use App\Support\EditorialRecipients;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The five-step wizard: Journal → Manuscript → Authors → Declarations → Review.
 *
 * The wizard promises the author two things on screen, and both are kept here:
 *
 *  - "Your progress is saved at every step." A draft is a real `submissions` row, so it
 *    survives a closed browser, a flat battery and a different machine — and it resumes at
 *    the step they had reached, not at step one with the fields still filled in.
 *
 *  - "Nothing is sent to an editor until the final one." A Draft is invisible to every
 *    editor query in the system (Submission::visibleToEditors), and it has no reference: a
 *    reference is minted at submission, so an abandoned draft does not burn a number out of
 *    the middle of the journal's sequence.
 */
final class SubmissionController extends Controller
{
    /** The limits the wizard also shows the author — keep in step with Submit.tsx TEXT_LIMITS. */
    public const ABSTRACT_MIN_WORDS = 30;

    public const ABSTRACT_MAX_WORDS = 500;

    public const ABSTRACT_MAX_CHARS = 5000;

    public const FUNDING_MAX_CHARS = 2000;

    /** keywords() has always stored at most 8, so that is the limit the author is told. */
    public const MAX_KEYWORDS = 8;

    public function create(Request $request): Response
    {
        return Inertia::render('Submit', [
            'journals' => $this->journals(),
            'types' => $this->types(),

            // Server-side draft resume is a signed-in convenience. A guest's progress lives
            // in the wizard's own state for the session; there is no account to tie a draft
            // to, and we do not create one just to submit a paper.
            'draft' => $request->user() ? $this->draft($request->user()) : null,

            'meta' => [
                'title' => 'Submit your research — '.config('app.name'),
                'description' => 'Format-free first submission to any Meridian journal.',
            ],
        ]);
    }

    /**
     * Save the draft. Called on EVERY step transition, so it must be cheap, forgiving and
     * silent: it validates nothing the author has not finished typing yet, and it never
     * bounces them backwards with an error for a field two steps ahead.
     */
    public function storeDraft(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'journal' => ['nullable', 'string', 'exists:journals,slug'],
            'title' => ['nullable', 'string', 'max:500'],
            'type' => ['nullable', 'string', 'max:255'],
            // Only CEILINGS here: a draft is half-typed by definition, so the content checks
            // wait for the real submission — but nothing may park megabytes in a draft row.
            'abstract' => ['nullable', 'string', 'max:'.self::ABSTRACT_MAX_CHARS],
            'keywords' => ['nullable', 'string', 'max:500'],
            'fileName' => ['nullable', 'string', 'max:255'],
            'authors' => ['nullable', 'array', 'max:50'],
            'authors.*.name' => ['nullable', 'string', 'max:255'],
            'authors.*.email' => ['nullable', 'string', 'max:255'],
            'authors.*.affiliation' => ['nullable', 'string', 'max:255'],
            'authors.*.corresponding' => ['nullable', 'boolean'],
            'funding' => ['nullable', 'string', 'max:'.self::FUNDING_MAX_CHARS],
            'ethics' => ['nullable', 'boolean'],
            'conflicts' => ['nullable', 'boolean'],
            'dataAvailable' => ['nullable', 'boolean'],
            'step' => ['nullable', 'integer', 'between:0,4'],
            'issue' => ['nullable', 'integer'],
        ]);

        $journal = filled($data['journal'] ?? null)
            ? Journal::where('slug', $data['journal'])->first()
            : null;

        // A submission cannot exist without a journal, and step 0 IS the journal choice —
        // so before it has been made there is nothing to save. The wizard only reaches step
        // 1 once a journal is chosen, so this costs the author nothing.
        if ($journal === null) {
            return back();
        }

        $user = $request->user();

        // A guest has no account to hang a draft off. The wizard keeps their progress in
        // its own state for the session; server-side persistence is a signed-in feature.
        if ($user === null) {
            return back();
        }

        $draft = $this->draftModel($user, $journal);

        $draft->fill([
            'journal_id' => $journal->id,
            'journal_section_id' => $this->sectionId($journal, $data['type'] ?? null),
            // A draft is forgiving: an issue that has since closed just becomes "no preference".
            'target_issue_id' => $this->openIssue($journal, $data['issue'] ?? null)?->id,
            'corresponding_author_id' => $user->id,
            'title' => (string) ($data['title'] ?? ''),
            'abstract' => $data['abstract'] ?? null,
            'keywords' => $this->keywords($data['keywords'] ?? null),
            'status' => SubmissionStatus::Draft,
            'funding' => $data['funding'] ?? null,
            'ethics_declared' => (bool) ($data['ethics'] ?? false),
            'conflicts_declared' => (bool) ($data['conflicts'] ?? false),
            'data_available' => (bool) ($data['dataAvailable'] ?? false),

            // The step is what makes "resumes where you left off" true rather than
            // approximately true.
            'draft_step' => $data['step'] ?? 0,

            // The NAME of the file only. The file itself is not uploaded on a draft save
            // (a File does not survive a JSON round-trip), and the wizard makes the author
            // re-attach it before submitting — printing a filename we cannot actually
            // upload would be a lie they discover when the manuscript arrives empty.
            'draft_file_name' => $data['fileName'] ?? null,
        ]);

        $isNew = ! $draft->exists;
        $draft->save();

        $this->syncDraftAuthors($draft, $data['authors'] ?? []);

        if ($isNew) {
            $draft->recordEvent('draft.created', ['journal_id' => $journal->id], $user);
        }

        return back();
    }

    public function store(Request $request, SubmitManuscriptAction $submit): RedirectResponse
    {
        // The error KEYS are part of the frontend contract: Submit.tsx maps 'journal' to
        // step 0, 'authors.*' to step 2, and so on, and jumps the author to the first step
        // that has one. Rename a key here and a server-side rejection becomes invisible.
        //
        // CONTENT CHECKS. The form is public — guests can submit — and what it collects is
        // shown to editors and, once accepted, published. SensibleText rejects one giant
        // unbroken "word", keyboard-mash ("fsfsfsfs…") and implausible word counts; the same
        // limits run in the wizard (Submit.tsx → TEXT_LIMITS), so an honest author hears about
        // them while typing and only a hand-crafted request ever meets them here.
        $data = $request->validate([
            'journal' => ['required', 'string', 'exists:journals,slug'],
            'title' => ['required', 'string', 'min:10', 'max:300', new SensibleText('The title', minWords: 3, maxWords: 40)],
            'type' => ['nullable', 'string', 'max:255'],
            'abstract' => [
                'required', 'string', 'max:'.self::ABSTRACT_MAX_CHARS,
                new SensibleText('The abstract', minWords: self::ABSTRACT_MIN_WORDS, maxWords: self::ABSTRACT_MAX_WORDS),
            ],
            'keywords' => ['nullable', 'string', 'max:500', $this->keywordsRule()],

            // The UI has always claimed 50 MB and 4 formats. This is what enforces them.
            'file' => ['required', 'file', 'max:51200', 'extensions:pdf,doc,docx,tex,zip'],

            'authors' => ['required', 'array', 'min:1', 'max:50'],
            // Letters (any script, with accents), spaces, hyphens, apostrophes and full stops:
            // "Md Emrul Easir V", "Siobhán O'Brien-Papé", "Dr. A. Rahim". No digits, no symbols.
            'authors.*.name' => [
                'required', 'string', 'min:2', 'max:100',
                "regex:/^[\\p{L}\\p{M}][\\p{L}\\p{M}\\s.'’\\-]*$/u",
                new SensibleText('An author name', maxWords: 8),
            ],
            'authors.*.email' => ['required', 'email', 'max:255'],
            'authors.*.affiliation' => ['nullable', 'string', 'max:255', new SensibleText('An affiliation', maxWords: 40)],
            'authors.*.corresponding' => ['nullable', 'boolean'],

            'funding' => ['nullable', 'string', 'max:'.self::FUNDING_MAX_CHARS, new SensibleText('The funding statement', maxWords: 300)],
            'ethics' => ['accepted'],
            'conflicts' => ['accepted'],
            'dataAvailable' => ['nullable', 'boolean'],
            'issue' => ['nullable', 'integer'],
        ], [
            'ethics.accepted' => 'Confirm the ethics statement to continue.',
            'conflicts.accepted' => 'Declare competing interests to continue.',
            'file.extensions' => 'Attach a PDF, DOCX, LaTeX source or ZIP archive.',
            'file.max' => 'That file is over the 50 MB limit — compress it, or attach the LaTeX source instead.',
            'title.min' => 'The title is too short — at least 10 characters.',
            'abstract.max' => 'The abstract is too long — at most '.self::ABSTRACT_MAX_CHARS.' characters.',
            'authors.*.name.regex' => 'An author name can contain only letters, spaces, hyphens, apostrophes and full stops.',
            'authors.*.name.min' => 'Enter the author’s full name.',
        ]);

        $journal = Journal::where('slug', $data['journal'])->firstOrFail();

        // The issue the author is aiming for. Unlike a draft save, a final submission is told
        // when its issue has closed in the meantime: silently dropping the choice would send
        // the editors a preference the author never made.
        $targetIssue = $this->openIssue($journal, $data['issue'] ?? null);
        if (filled($data['issue'] ?? null) && $targetIssue === null) {
            throw ValidationException::withMessages([
                'issue' => 'That issue is no longer open for submissions. Choose another, or "Next available issue".',
            ]);
        }
        $data['target_issue_id'] = $targetIssue?->id;

        $submission = $submit->execute($request->user(), $journal, $data, $request->file('file'));

        // The receipt. Dispatched AFTER the action returns — i.e. post-commit — and queued,
        // so a submission is never acknowledged by email unless it actually saved. Guest-safe:
        // the address is the corresponding SubmissionAuthor's, which exists with or without an
        // account.
        if ($email = EditorialRecipients::correspondingAuthorEmail($submission)) {
            Mail::to($email)->send(new SubmissionReceivedMail($submission));
        }

        // The Success screen prints ONLY what the server issued. A manuscript ID that
        // exists in no database is worse than none at all — the author quotes it, and the
        // editorial office has never heard of it.
        return back()
            ->with('success', 'Your manuscript is with the editorial office.')
            ->with('submission', [
                'reference' => $submission->reference,
                'journal' => $journal->title,
                'title' => $submission->title,
                'issue' => $targetIssue?->labelWithSeason(),
                'medianDaysToDecision' => EditorialMetrics::medianDaysToFirstDecision($journal)
                    ?? $journal->metric?->median_days_to_decision,
            ]);
    }

    /**
     * The author uploads a revised manuscript in response to a revise-and-resubmit decision.
     * Closes the loop the pipeline could open but not finish. Authorised by SubmissionPolicy:
     * only the owner, only while the status is RevisionsRequested.
     */
    public function revision(Request $request, Submission $submission, AuthorRevisionAction $revise): RedirectResponse
    {
        $this->authorize('uploadRevision', $submission);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:51200', 'extensions:pdf,doc,docx,tex,zip'],
            'note' => ['nullable', 'string', 'max:5000', new SensibleText('The note', maxWords: 1000)],
        ], [
            'file.extensions' => 'Attach a PDF, DOCX, LaTeX source or ZIP archive.',
            'file.max' => 'That file is over the 50 MB limit — compress it, or attach the LaTeX source instead.',
        ]);

        $revise->execute($submission, $request->user(), $request->file('file'), $data['note'] ?? null);

        // Let the editors know the revision landed. Post-commit, queued.
        Notification::send(
            EditorialRecipients::editorsOf($submission->journal),
            new RevisionSubmittedNotification($submission),
        );

        return back()->with('success', 'Your revised manuscript has been sent to the editor.');
    }

    /** @return array<int, array<string, mixed>> */
    private function journals(): array
    {
        return Journal::query()
            ->where('is_active', true)
            ->with('metric')
            ->orderBy('title')
            ->get()
            ->map(fn (Journal $journal): array => [
                'slug' => $journal->slug,
                'title' => $journal->title,
                'description' => $journal->description,

                // Live from our own rows first, falling back to the scheduled roll-up. NULL
                // where a launch journal has decided nothing yet — the card then shows no
                // statistic at all, rather than "0% acceptance".
                'acceptanceRate' => EditorialMetrics::acceptanceRate($journal)
                    ?? $journal->metric?->acceptance_rate,
                'medianDaysToDecision' => EditorialMetrics::medianDaysToFirstDecision($journal)
                    ?? $journal->metric?->median_days_to_decision,

                // Where the journal is now, so the author can see what they are joining.
                'latestIssue' => $this->latestIssue($journal),

                // The issues the editors have opened for submissions (unpublished ones).
                'openIssues' => Issue::query()
                    ->openForSubmissions($journal)
                    ->with('volume')
                    ->get()
                    ->map(fn (Issue $issue): array => [
                        'id' => $issue->id,
                        'label' => $issue->labelWithSeason(),
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /** @return array{label: string, url: string, cover: string|null, articles: int}|null */
    private function latestIssue(Journal $journal): ?array
    {
        $latest = IssueCardResource::query($journal)->first();

        return $latest === null ? null : [
            'label' => $latest->labelWithSeason(),
            'url' => $latest->url(),
            'cover' => $latest->coverMedia?->url,
            'articles' => (int) $latest->published_articles_count,
        ];
    }

    /**
     * Keywords arrive as one comma-separated string. Each one is checked on its own: at most
     * MAX_KEYWORDS of them, each at most 60 characters and 6 words, and each real text.
     */
    private function keywordsRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            // Split here rather than via keywords(), which silently keeps only the first 8 —
            // the author should be told, not have the rest disappear.
            $keywords = array_values(array_filter(array_map('trim', explode(',', is_string($value) ? $value : ''))));

            if (count($keywords) > self::MAX_KEYWORDS) {
                $fail('Enter at most '.self::MAX_KEYWORDS.' keywords.');

                return;
            }

            foreach ($keywords as $keyword) {
                if (mb_strlen($keyword) > 60) {
                    $fail('The keyword “'.str($keyword)->limit(30).'” is too long — at most 60 characters.');

                    return;
                }

                if ($problem = SensibleText::problem($keyword, 'A keyword', maxWords: 6)) {
                    $fail($problem);

                    return;
                }
            }
        };
    }

    /** An unpublished issue of this journal, or null: never a published or foreign one. */
    private function openIssue(Journal $journal, mixed $id): ?Issue
    {
        if (blank($id)) {
            return null;
        }

        return Issue::query()->openForSubmissions($journal)->with('volume')->find((int) $id);
    }

    /**
     * The article types the wizard offers. Sections are per-journal, but the wizard has one
     * global picker, so this is the union of the active journals' section names — and the
     * chosen name is mapped back to a section OF THE CHOSEN JOURNAL on save.
     *
     * @return array<int, string>
     */
    private function types(): array
    {
        return JournalSection::query()
            ->where('is_active', true)
            ->orderBy('sequence')
            ->pluck('name')
            // DISTINCT + ORDER BY on a non-selected column is rejected outright under
            // MySQL 5.7's ONLY_FULL_GROUP_BY, so the dedupe happens here.
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<string, mixed>|null */
    private function draft(User $user): ?array
    {
        $draft = Submission::query()
            ->with(['journal', 'section', 'authors'])
            ->where('corresponding_author_id', $user->id)
            ->where('status', SubmissionStatus::Draft)
            ->orderByDesc('updated_at')
            ->first();

        if ($draft === null) {
            return null;
        }

        return [
            // The SLUG, not the title: the radio group submits a key, not a display string.
            'journal' => $draft->journal?->slug ?? '',
            'issue' => $draft->target_issue_id,
            'title' => (string) $draft->title,
            'type' => $draft->section?->name ?? '',
            'abstract' => (string) $draft->abstract,
            'keywords' => implode(', ', $draft->keywords ?? []),
            'fileName' => $draft->draft_file_name,
            'authors' => $draft->authors
                ->map(fn ($author): array => [
                    'name' => $author->name,
                    'email' => $author->email,
                    'affiliation' => (string) $author->affiliation,
                    'corresponding' => $author->is_corresponding,
                ])
                ->values()
                ->all(),
            'funding' => (string) $draft->funding,
            'ethics' => $draft->ethics_declared,
            'conflicts' => $draft->conflicts_declared,
            'dataAvailable' => $draft->data_available,
            'step' => $draft->draft_step ?? 0,
        ];
    }

    /** One live draft per author per journal — resumed, not duplicated. */
    private function draftModel(User $user, Journal $journal): Submission
    {
        return Submission::query()
            ->where('corresponding_author_id', $user->id)
            ->where('journal_id', $journal->id)
            ->where('status', SubmissionStatus::Draft)
            ->orderByDesc('id')
            ->first()
            ?? new Submission;
    }

    /** @param  array<int, array<string, mixed>>  $authors */
    private function syncDraftAuthors(Submission $draft, array $authors): void
    {
        // The wizard always sends the complete list, so a wholesale replace is correct and a
        // diff would leave removed co-authors behind. A half-typed author row is kept: this
        // is a draft, and losing what they typed is the one thing it must never do.
        $draft->authors()->delete();

        foreach (array_values($authors) as $i => $author) {
            if (blank($author['name'] ?? null) && blank($author['email'] ?? null)) {
                continue;
            }

            $draft->authors()->create([
                'name' => (string) ($author['name'] ?? ''),
                'email' => (string) ($author['email'] ?? ''),
                'affiliation' => $author['affiliation'] ?? null,
                'is_corresponding' => $i === 0 || (bool) ($author['corresponding'] ?? false),
                'sequence' => $i + 1,
            ]);
        }
    }

    private function sectionId(Journal $journal, ?string $type): ?int
    {
        return blank($type) ? null : $journal->sections()->where('name', $type)->value('id');
    }

    /** @return array<int, string> */
    private function keywords(?string $keywords): array
    {
        return collect(explode(',', (string) $keywords))
            ->map(fn (string $keyword): string => trim($keyword))
            ->filter()
            ->take(8)
            ->values()
            ->all();
    }
}
