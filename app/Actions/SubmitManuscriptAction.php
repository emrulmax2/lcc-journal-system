<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SubmissionFileType;
use App\Enums\SubmissionStage;
use App\Enums\SubmissionStatus;
use App\Models\Journal;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a draft (or nothing at all) into a manuscript sitting in an editor's queue.
 *
 * Two things here are not negotiable:
 *
 *  1. THE REFERENCE IS RACE-SAFE. It is the number the author quotes in an email and the
 *     editorial office looks up. Two manuscripts sharing one reference means an editor
 *     opens the wrong paper, and the author is told about a decision on someone else's
 *     work. The sequence is read under `lockForUpdate` inside the same transaction that
 *     writes it — an InnoDB gap lock, so a concurrent submission to the same journal in
 *     the same year blocks rather than reading the same "last" row. The unique index on
 *     `reference` is the backstop, and a duplicate-key violation is retried rather than
 *     surfaced: if the lock ever fails us, the author must still get a reference.
 *
 *  2. THE DECLARATIONS ARE TIMESTAMPED. Ethics, competing interests and data availability
 *     are a compliance record, not a checkbox — `declarations_at` is when the author
 *     affirmed them, and it is what an integrity investigation asks for years later.
 */
final class SubmitManuscriptAction
{
    /** A reference must be issued. If the lock somehow lets two through, retry rather than fail. */
    private const MAX_ATTEMPTS = 3;

    /**
     * @param  array{title: string, type?: ?string, abstract: string, keywords?: ?string,
     *               authors: array<int, array{name: string, email: string, affiliation?: ?string,
     *               corresponding?: bool}>, funding?: ?string, ethics: bool, conflicts: bool,
     *               dataAvailable?: bool}  $data
     */
    public function execute(?User $author, Journal $journal, array $data, UploadedFile $file): Submission
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(fn (): Submission => $this->submit($author, $journal, $data, $file));
            } catch (QueryException $e) {
                // 23000 = integrity constraint violation, i.e. two submissions raced onto
                // the same reference. Try again with a freshly read sequence.
                if ($attempt >= self::MAX_ATTEMPTS || ! $this->isDuplicateReference($e)) {
                    throw $e;
                }
            }
        }
    }

    /** @param  array<string, mixed>  $data */
    private function submit(?User $author, Journal $journal, array $data, UploadedFile $file): Submission
    {
        // Resume the author's existing draft if they have one, so that the four steps of
        // typing they already saved become THIS manuscript rather than a duplicate row
        // that quietly outlives it. A guest has no server-side draft to resume.
        $submission = ($author ? $this->draftFor($author, $journal) : null)
            ?? new Submission(['journal_id' => $journal->id]);

        $submission->fill([
            'reference' => $this->nextReference($journal),
            'journal_id' => $journal->id,
            'journal_section_id' => $this->sectionId($journal, $data['type'] ?? null),

            // NULL for a guest — the corresponding author is the submission_author flagged
            // is_corresponding, identified by the email they entered, not by a user account.
            'corresponding_author_id' => $author?->id,

            'title' => $data['title'],
            'abstract' => $data['abstract'],
            'keywords' => $this->keywords($data['keywords'] ?? null),

            'status' => SubmissionStatus::Submitted,
            'stage' => SubmissionStage::Submitted,

            'funding' => $data['funding'] ?? null,
            'ethics_declared' => (bool) $data['ethics'],
            'conflicts_declared' => (bool) $data['conflicts'],
            'data_available' => (bool) ($data['dataAvailable'] ?? false),
            'declarations_at' => now(),

            'submitted_at' => now(),

            // The draft's scratch state is over.
            'draft_step' => null,
            'draft_file_name' => null,
        ]);

        $submission->save();

        $this->syncAuthors($submission, $data['authors']);
        $this->storeManuscript($submission, $file, $author);

        $submission->recordEvent('submission.submitted', [
            'reference' => $submission->reference,
            'journal_id' => $journal->id,
            'file' => $file->getClientOriginalName(),
        ], $author);

        return $submission;
    }

    /**
     * {ABBREV}-{YEAR}-{0000}. The sequence is per journal, per year — JCDMS-2026-0417 is
     * the 417th manuscript that journal received in 2026, and that is the number an editor
     * expects to be able to reason about.
     */
    private function nextReference(Journal $journal): string
    {
        $prefix = $this->prefix($journal).'-'.now()->year;

        // lockForUpdate INSIDE the caller's transaction. Two authors submitting at the
        // same instant serialise here instead of both reading sequence 416.
        $last = Submission::query()
            ->where('journal_id', $journal->id)
            ->where('reference', 'like', $prefix.'-%')
            // Lexicographic ordering breaks at 10,000 ("...-10000" sorts below "...-9999").
            // Order by the numeric tail so the sequence keeps climbing past the padding.
            ->orderByRaw('CAST(SUBSTRING_INDEX(reference, "-", -1) AS UNSIGNED) DESC')
            ->lockForUpdate()
            ->value('reference');

        $next = $last === null
            ? 1
            : ((int) Str::afterLast($last, '-')) + 1;

        return sprintf('%s-%04d', $prefix, $next);
    }

    /**
     * "JCD&MS" is the citation abbreviation; a reference is typed into search boxes and
     * pasted into emails, so it is stripped to A-Z0-9. Falls back to the slug, and then to
     * the platform's own prefix, because a journal with no abbreviation must still be able
     * to receive a manuscript.
     */
    private function prefix(Journal $journal): string
    {
        $source = filled($journal->abbreviation) ? $journal->abbreviation : (string) $journal->slug;

        $clean = Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $source) ?? '');

        return $clean === '' ? 'MRDN' : Str::limit($clean, 8, '');
    }

    private function draftFor(User $author, Journal $journal): ?Submission
    {
        return Submission::query()
            ->where('corresponding_author_id', $author->id)
            ->where('journal_id', $journal->id)
            ->where('status', SubmissionStatus::Draft)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();
    }

    private function sectionId(Journal $journal, ?string $type): ?int
    {
        if (blank($type)) {
            return null;
        }

        return $journal->sections()->where('name', $type)->value('id');
    }

    /** @return array<int, string> */
    private function keywords(?string $keywords): array
    {
        return collect(explode(',', (string) $keywords))
            ->map(fn (string $k): string => trim($k))
            ->filter()
            ->take(8)          // the form says "up to eight", so the server is what enforces it
            ->values()
            ->all();
    }

    /** @param  array<int, array<string, mixed>>  $authors */
    private function syncAuthors(Submission $submission, array $authors): void
    {
        // A resubmitted draft replaces its author list wholesale — the wizard sends the
        // complete list every time, and a diff would leave removed co-authors behind.
        $submission->authors()->delete();

        foreach (array_values($authors) as $i => $author) {
            $submission->authors()->create([
                'name' => $author['name'],
                'email' => $author['email'],
                'affiliation' => $author['affiliation'] ?? null,
                // The wizard's first row IS the corresponding author, and says so on screen.
                'is_corresponding' => $i === 0 || (bool) ($author['corresponding'] ?? false),
                'sequence' => $i + 1,
            ]);
        }
    }

    /**
     * Versioned, never overwritten. The version reviewer 2 actually read must still be
     * retrievable when the decision is challenged.
     */
    private function storeManuscript(Submission $submission, UploadedFile $file, ?User $author): void
    {
        $version = (int) $submission->files()
            ->whereIn('type', [SubmissionFileType::Manuscript, SubmissionFileType::Revision])
            ->max('version') + 1;

        // The PRIVATE disk. A manuscript under review on a guessable public URL is a scoop
        // waiting to happen.
        $path = $file->store('submissions/'.$submission->id, 'private');

        $submission->files()->create([
            'version' => $version,
            'type' => $version === 1 ? SubmissionFileType::Manuscript : SubmissionFileType::Revision,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => $author?->id,
        ]);
    }

    private function isDuplicateReference(QueryException $e): bool
    {
        return (string) $e->getCode() === '23000';
    }
}
