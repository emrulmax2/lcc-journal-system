<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AssignReviewerAction;
use App\Actions\RespondToInvitationAction;
use App\Actions\SubmitReviewAction;
use App\Actions\WithdrawInvitationAction;
use App\Enums\Recommendation;
use App\Models\ReviewAssignment;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\ReviewDeclinedNotification;
use App\Notifications\ReviewInvitationNotification;
use App\Support\EditorialRecipients;
use App\Support\ReviewerPool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/**
 * The reviewer's side of the loop: invitation → accept/decline → report.
 *
 * Every endpoint is authorised against ReviewAssignmentPolicy, which is what stops one
 * reviewer answering, reading or reporting on another reviewer's invitation. The
 * authorisation is by ASSIGNMENT, not by submission: two reviewers on one manuscript have
 * exactly the same relationship to it and only their own assignment tells them apart.
 */
final class ReviewController extends Controller
{
    public function accept(Request $request, ReviewAssignment $assignment, RespondToInvitationAction $respond): RedirectResponse
    {
        $this->authorize('respond', $assignment);

        $respond->execute($assignment, true, $request->user());

        return back()->with('success', 'Thank you — the manuscript is in your review queue.');
    }

    public function decline(Request $request, ReviewAssignment $assignment, RespondToInvitationAction $respond): RedirectResponse
    {
        $this->authorize('respond', $assignment);

        $respond->execute($assignment, false, $request->user());

        // Makes "The editor has been notified" TRUE. Before this it was a promise the system
        // did not keep. Post-commit, queued. The editors chose this reviewer, so naming them
        // is not a leak.
        $submission = $assignment->round?->submission;

        if ($submission !== null) {
            Notification::send(
                EditorialRecipients::editorsOf($submission->journal),
                new ReviewDeclinedNotification($assignment),
            );
        }

        return back()->with('success', 'Invitation declined. The editor has been notified.');
    }

    public function report(Request $request, ReviewAssignment $assignment, SubmitReviewAction $submitReview): RedirectResponse
    {
        $this->authorize('submitReport', $assignment);

        $data = $request->validate([
            'recommendation' => ['required', Rule::enum(Recommendation::class)],
            'comments_to_author' => ['required', 'string'],

            // CONFIDENTIAL. Optional, and it goes to the editor alone — no author-facing
            // response in this system ever reads this column. See Review and
            // SubmissionPresenter.
            'comments_to_editor' => ['nullable', 'string'],
        ]);

        $submitReview->execute(
            $assignment,
            Recommendation::from($data['recommendation']),
            $data['comments_to_author'],
            $data['comments_to_editor'] ?? null,
            $request->user(),
        );

        return back()->with('success', 'Your report has been sent to the handling editor.');
    }

    /**
     * Invite a reviewer. There is no UI for this yet — the Dashboard is read-only about
     * reviewers — but the transition has to exist somewhere authorised and audited rather
     * than only in a test, because it is the one an author's challenge turns on: "who
     * assigned that reviewer, and when".
     */
    public function invite(Request $request, Submission $submission, AssignReviewerAction $assign): RedirectResponse
    {
        $this->authorize('assignReviewers', $submission);

        // Scoped to THIS journal's reviewer pool, not `exists:users,id`. Anyone may hold the
        // reviewer role on some other journal, or none at all; inviting them here would put a
        // person with no standing on the manuscript into a confidential review. The pool is
        // the same set the invite form offers (ReviewerPool::forJournal).
        $data = $request->validate([
            'reviewer_id' => ['required', 'integer', Rule::in(ReviewerPool::reviewerIdsOn($submission->journal))],
            'due_at' => ['nullable', 'date', 'after:today'],
        ]);

        $assignment = $assign->execute(
            $submission,
            User::findOrFail($data['reviewer_id']),
            $request->user(),
            filled($data['due_at'] ?? null) ? now()->parse($data['due_at']) : null,
        );

        // The invitation email. Post-commit (the action returned), queued.
        $assignment->reviewer->notify(new ReviewInvitationNotification($assignment));

        return back()->with('success', 'The reviewer has been invited.');
    }

    /**
     * Withdraw an outstanding invitation so a replacement can be invited. Authorised the same
     * way as inviting — the people who choose reviewers are the people who un-choose them.
     */
    public function withdraw(Request $request, ReviewAssignment $assignment, WithdrawInvitationAction $withdraw): RedirectResponse
    {
        $submission = $assignment->round?->submission;
        abort_if($submission === null, 404);

        $this->authorize('assignReviewers', $submission);

        $withdraw->execute($assignment, $request->user());

        return back()->with('success', 'The invitation has been withdrawn.');
    }
}
