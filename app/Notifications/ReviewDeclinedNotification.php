<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ReviewAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the editorial staff a reviewer has declined, so they can invite a replacement. This
 * is what makes the "The editor has been notified" line in ReviewController@decline TRUE —
 * before it, that was a promise the system did not keep.
 *
 * The recipients are editors, who chose the reviewer, so naming the reviewer is not a leak.
 */
class ReviewDeclinedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ReviewAssignment $assignment) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $submission = $this->assignment->round->submission;
        $reviewer = $this->assignment->reviewer?->fullName() ?? 'A reviewer';

        return (new MailMessage)
            ->subject("Reviewer declined — {$submission->reference}")
            ->greeting('A reviewer has declined')
            ->line("{$reviewer} has declined to review \"{$submission->title}\" ({$submission->reference}).")
            ->action('Invite a replacement', url("/admin/submissions/{$submission->id}"))
            ->line('You may want to invite another reviewer to keep the round moving.');
    }
}
