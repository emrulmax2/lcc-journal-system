<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the editorial staff a revised manuscript has arrived, so they can pick the round back
 * up — invite reviewers again, or decide. Queued, dispatched after the revision commits.
 */
class RevisionSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Submission $submission) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Revision received — {$this->submission->reference}")
            ->greeting('A revised manuscript has arrived')
            ->line("The author has submitted a revision of \"{$this->submission->title}\" ({$this->submission->reference}).")
            ->action('Open the submission', url("/admin/submissions/{$this->submission->id}"))
            ->line('You can invite reviewers for another round, or record a decision.');
    }
}
