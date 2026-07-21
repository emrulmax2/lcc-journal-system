<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The editor's decision, sent to the corresponding author. Carries the decision letter
 * verbatim — the reasoning the author acts on, and the first thing asked for if the decision
 * is later challenged.
 *
 * To an EMAIL ADDRESS (guest-safe). ShouldQueue, dispatched after the decision transaction
 * commits.
 */
class DecisionLetterMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Submission $submission,
        public readonly string $decisionLabel,
        public readonly string $letter,
    ) {}

    public function envelope(): Envelope
    {
        $reference = $this->submission->reference ?? 'your manuscript';

        return new Envelope(subject: "Decision on {$reference}: {$this->decisionLabel}");
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.decision-letter',
            with: [
                'reference' => $this->submission->reference,
                'title' => $this->submission->title,
                'journalTitle' => $this->submission->journal?->title ?? 'the journal',
                'decisionLabel' => $this->decisionLabel,
                'letter' => $this->letter,
            ],
        );
    }
}
