<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ReviewerStatus;
use App\Models\ReviewAssignment;
use App\Notifications\ReviewReminderNotification;
use Illuminate\Console\Command;

/**
 * Chases reviewers whose report is due soon or already overdue.
 *
 * A review that quietly sits past its deadline is the single biggest source of slow decisions,
 * and nothing on the site surfaces it to the reviewer — only the editor sees the overdue flag.
 * This closes that gap without spamming: a reviewer is reminded at most once per cadence.
 */
class SendReviewRemindersCommand extends Command
{
    protected $signature = 'reviews:remind
        {--soon-days=3 : Remind when the report is due within this many days}
        {--cadence-days=7 : Minimum days between reminders to the same reviewer}';

    protected $description = 'Email reviewers whose report is due soon or overdue';

    public function handle(): int
    {
        $soonDays = (int) $this->option('soon-days');
        $cadenceDays = (int) $this->option('cadence-days');

        $cutoff = now()->copy()->subDays($cadenceDays);

        $assignments = ReviewAssignment::query()
            // Still owed to the editor. A submitted or declined report needs no reminder.
            ->whereIn('status', [ReviewerStatus::Invited, ReviewerStatus::Accepted])
            // Due within the window, or already past due.
            ->where('due_at', '<=', now()->copy()->addDays($soonDays))
            // Not reminded recently — chase, do not spam.
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_reminded_at')->orWhere('last_reminded_at', '<=', $cutoff);
            })
            ->with(['reviewer', 'round.submission.journal'])
            ->get();

        if ($assignments->isEmpty()) {
            $this->info('No reviewers need reminding.');

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($assignments as $assignment) {
            $reviewer = $assignment->reviewer;
            $submission = $assignment->round?->submission;

            // A reviewer with no account, or an assignment whose round/submission has gone,
            // cannot be reminded. Skip rather than crash the whole run.
            if ($reviewer === null || $submission === null) {
                continue;
            }

            $overdue = $assignment->due_at !== null && $assignment->due_at->isPast();

            $reviewer->notify(new ReviewReminderNotification($assignment, $overdue));

            $assignment->forceFill(['last_reminded_at' => now()])->save();

            // A scheduled event has no human actor — recordEvent's user is null on purpose.
            $submission->recordEvent('reviewer.reminded', [
                'assignment_id' => $assignment->id,
                'overdue' => $overdue,
            ]);

            $sent++;
        }

        $this->info("Reminded {$sent} reviewer(s).");

        return self::SUCCESS;
    }
}
