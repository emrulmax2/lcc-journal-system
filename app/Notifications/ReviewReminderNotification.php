<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ReviewAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A nudge to a reviewer whose report is due soon or overdue. Sent by the scheduled
 * SendReviewRemindersCommand, which spaces reminders out so a late reviewer is chased, not
 * spammed.
 */
class ReviewReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ReviewAssignment $assignment,
        public readonly bool $overdue,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $submission = $this->assignment->round->submission;
        $journal = $submission->journal?->title ?? 'a Meridian journal';
        $due = $this->assignment->due_at?->toFormattedDateString();

        $message = (new MailMessage)
            ->subject(($this->overdue ? 'Overdue: ' : 'Reminder: ')."your review of {$submission->reference}");

        if ($this->overdue) {
            $message->greeting('Your review is overdue')
                ->line("Your report on \"{$submission->title}\" for *{$journal}* was due on **{$due}**.");
        } else {
            $message->greeting('A gentle reminder')
                ->line("Your report on \"{$submission->title}\" for *{$journal}* is due on **{$due}**.");
        }

        return $message
            ->action('Open your review', url('/dashboard'))
            ->line('If you can no longer review this manuscript, decline from your dashboard and we will find another reviewer.');
    }
}
