<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\DecisionType;
use App\Models\EditorialDecision;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The editor decides. This CLOSES the round.
 *
 * On Accept the submission becomes an Article — see ConvertSubmissionToArticleAction. That
 * conversion runs inside this transaction because a submission marked Accepted with no
 * article, or an article with no submission pointing at it, is a state nobody downstream
 * knows how to interpret.
 */
final class RecordDecisionAction
{
    public function __construct(private readonly ConvertSubmissionToArticleAction $convert) {}

    public function execute(
        Submission $submission,
        DecisionType $decision,
        string $body,
        User $editor,
    ): EditorialDecision {
        if ($submission->isDraft()) {
            throw ValidationException::withMessages([
                'submission' => 'This manuscript is still a draft. There is nothing to decide on yet.',
            ]);
        }

        if ($submission->status->isDecided()) {
            // Accepted and Rejected are terminal. Re-deciding would leave two contradictory
            // decisions on one manuscript and no way to tell which one the author was told.
            throw ValidationException::withMessages([
                'submission' => 'This manuscript has already been decided.',
            ]);
        }

        return DB::transaction(function () use ($submission, $decision, $body, $editor): EditorialDecision {
            // NULL for a desk rejection — a real decision, taken before any round existed.
            $round = $submission->currentRound();

            $record = $submission->decisions()->create([
                'review_round_id' => $round?->id,
                'editor_id' => $editor->id,
                'decision' => $decision,
                'body' => $body,
                'decided_at' => now(),
            ]);

            if ($round !== null && $round->isOpen()) {
                $round->forceFill(['closed_at' => now()])->save();

                $submission->recordEvent('round.closed', [
                    'review_round_id' => $round->id,
                    'round_number' => $round->round_number,
                ], $editor);
            }

            $submission->forceFill([
                'status' => $decision->resultingStatus(),
                'stage' => $decision->resultingStage(),
            ])->save();

            $submission->recordEvent('decision.recorded', [
                'decision_id' => $record->id,
                'decision' => $decision->value,
                'review_round_id' => $round?->id,
                'days_to_decision' => $submission->daysToFirstDecision(),
            ], $editor);

            if ($decision === DecisionType::Accept) {
                $this->convert->execute($submission, $editor);
            }

            return $record;
        });
    }
}
