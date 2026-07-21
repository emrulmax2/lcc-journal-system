<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\SubmissionStage;
use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Models\SubmissionDiscussion;
use App\Support\EditorialRecipients;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The internal editorial forum on a manuscript. Append-only: a thread is opened, and messages
 * are added; nothing here edits or deletes, because the discussion is part of the same
 * editorial record the audit trail is.
 *
 * Participants are drawn from the journal's EDITORIAL staff only. The author is never one, and
 * — to keep single-blind review intact — reviewers are not offered here either; an editors-only
 * forum cannot leak one reviewer's identity to another.
 */
final class SubmissionDiscussionController extends Controller
{
    public function store(Request $request, Submission $submission): RedirectResponse
    {
        $this->authorize('viewAllSubmissions', $submission->journal);

        $editorIds = EditorialRecipients::editorsOf($submission->journal)->pluck('id')->all();

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'stage' => ['nullable', Rule::enum(SubmissionStage::class)],
            'body' => ['required', 'string'],
            'participants' => ['array'],
            // Only this journal's editorial staff may be added — never the author, never an
            // arbitrary account.
            'participants.*' => ['integer', Rule::in($editorIds)],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($submission, $data, $user): void {
            $discussion = $submission->discussions()->create([
                'stage' => filled($data['stage'] ?? null) ? SubmissionStage::from((int) $data['stage']) : null,
                'subject' => $data['subject'],
                'created_by' => $user->id,
            ]);

            // The creator is always on the thread; the rest are the selected editorial staff.
            $participantIds = collect($data['participants'] ?? [])
                ->push($user->id)
                ->unique()
                ->values();

            $discussion->participants()->syncWithoutDetaching(
                $participantIds->mapWithKeys(fn (int $id): array => [$id => ['added_by' => $user->id]])->all()
            );

            $discussion->messages()->create([
                'user_id' => $user->id,
                'body' => $data['body'],
            ]);

            $submission->recordEvent('discussion.started', [
                'discussion_id' => $discussion->id,
                'subject' => $discussion->subject,
            ], $user);
        });

        return back()->with('success', 'Discussion started.');
    }

    public function reply(Request $request, SubmissionDiscussion $discussion): RedirectResponse
    {
        $this->authorize('reply', $discussion);

        $data = $request->validate([
            'body' => ['required', 'string'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($discussion, $data, $user): void {
            $discussion->messages()->create([
                'user_id' => $user->id,
                'body' => $data['body'],
            ]);

            // Anyone who joins the conversation becomes a participant, so a later notification
            // reaches everyone actually talking, not only the original invitees.
            $discussion->participants()->syncWithoutDetaching([
                $user->id => ['added_by' => $user->id],
            ]);
        });

        return back()->with('success', 'Reply posted.');
    }
}
