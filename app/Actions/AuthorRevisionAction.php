<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SubmissionFileType;
use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The author's response to a "revise and resubmit" decision.
 *
 * This is the seam that was MISSING — the pipeline could ask for revisions but had nowhere
 * for the revised manuscript to arrive, so a Minor/Major revision was a dead end. The file is
 * appended as a new version (never overwriting the version a reviewer read — that must stay
 * retrievable when a decision is challenged), and the manuscript moves back into active review
 * so the editor sees it as theirs to act on again.
 *
 * The round is NOT reopened here. After a revision the editor decides what happens next —
 * a second round of review (AssignReviewerAction opens one) or a fresh decision — and forcing
 * a round open here would presume that choice.
 */
final class AuthorRevisionAction
{
    public function execute(Submission $submission, User $author, UploadedFile $file, ?string $note = null): SubmissionFile
    {
        if ($submission->status !== SubmissionStatus::RevisionsRequested) {
            throw ValidationException::withMessages([
                'submission' => 'This manuscript is not awaiting a revision.',
            ]);
        }

        return DB::transaction(function () use ($submission, $author, $file, $note): SubmissionFile {
            $version = (int) $submission->files()
                ->whereIn('type', [SubmissionFileType::Manuscript, SubmissionFileType::Revision])
                ->max('version') + 1;

            $path = $file->store('submissions/'.$submission->id, 'private');

            $revision = $submission->files()->create([
                'version' => $version,
                'type' => SubmissionFileType::Revision,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
                'uploaded_by' => $author->id,
            ]);

            // Back with the editor. Status only — the stage stays where the decision left it,
            // and the editor's next action (invite reviewers / decide) moves it on.
            $submission->forceFill(['status' => SubmissionStatus::UnderReview])->save();

            $submission->recordEvent('revision.submitted', [
                'file' => $file->getClientOriginalName(),
                'version' => $version,
                'note' => $note,
            ], $author);

            return $revision;
        });
    }
}
