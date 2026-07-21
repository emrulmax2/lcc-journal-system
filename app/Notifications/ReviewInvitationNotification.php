<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ReviewAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The invitation email a reviewer gets when an editor assigns them. Queued, dispatched after
 * the assignment transaction commits.
 *
 * The manuscript title is not confidential to the person being asked to review it. The
 * reviewer accepts or declines from their dashboard — this email does not carry the decision,
 * it carries the ask.
 */
class ReviewInvitationNotification extends Notification implements ShouldQueue
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
        $journal = $submission->journal?->title ?? 'a Meridian journal';
        $due = $this->assignment->due_at?->toFormattedDateString();

        return (new MailMessage)
            ->subject("Invitation to review — {$submission->reference}")
            ->greeting('You have been invited to review')
            ->line("*{$journal}* would like your assessment of \"{$submission->title}\".")
            ->when($due !== null, fn (MailMessage $m) => $m->line("A report would be appreciated by **{$due}**."))
            ->action('Accept or decline', url('/dashboard'))
            ->line('You can accept or decline from your dashboard. Declining is completely fine — it just lets us find someone else quickly.');
    }
}
