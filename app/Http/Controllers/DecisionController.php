<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RecordDecisionAction;
use App\Enums\DecisionType;
use App\Mail\DecisionLetterMail;
use App\Models\Submission;
use App\Support\EditorialRecipients;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * The editor decides. On Accept this is also the moment a manuscript becomes an Article —
 * a DRAFT article, which still has to pass PublishArticleAction's gate before it gets a URL
 * and a DOI. See ConvertSubmissionToArticleAction.
 */
final class DecisionController extends Controller
{
    public function store(Request $request, Submission $submission, RecordDecisionAction $record): RedirectResponse
    {
        $this->authorize('decide', $submission);

        $data = $request->validate([
            'decision' => ['required', Rule::enum(DecisionType::class)],

            // The letter to the author. Required: a decision with no reasoning is not one
            // an author can act on, and it is the first thing asked for when a decision is
            // challenged.
            'body' => ['required', 'string'],
        ]);

        $decision = DecisionType::from($data['decision']);

        $record->execute($submission, $decision, $data['body'], $request->user());

        // The decision letter to the author. Post-commit, queued, guest-safe (the address is
        // the corresponding author's, account or not). The letter body is sent verbatim.
        if ($email = EditorialRecipients::correspondingAuthorEmail($submission)) {
            Mail::to($email)->send(new DecisionLetterMail($submission, $decision->label(), $data['body']));
        }

        return back()->with('success', "Decision recorded: {$decision->label()}.");
    }
}
