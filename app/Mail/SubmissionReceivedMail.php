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
 * The receipt an author gets the moment their manuscript lands. Sent to an EMAIL ADDRESS, not
 * a User — the corresponding author may be a guest with no account.
 *
 * ShouldQueue + dispatched after the submit transaction commits: no receipt is ever sent for
 * a submission that then failed to save.
 */
class SubmissionReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Submission $submission) {}

    public function envelope(): Envelope
    {
        $reference = $this->submission->reference ?? 'your manuscript';

        return new Envelope(subject: "We've received your submission — {$reference}");
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.submission-received',
            with: [
                'reference' => $this->submission->reference,
                'title' => $this->submission->title,
                'journalTitle' => $this->submission->journal?->title ?? 'the journal',
            ],
        );
    }
}
