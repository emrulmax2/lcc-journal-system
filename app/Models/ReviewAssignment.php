<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReviewerStatus;
use Database\Factories\ReviewAssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * An invitation to review one manuscript, in one round.
 *
 * `reviewer` IS THE IDENTITY THAT MUST NOT LEAK. Never eager-load it into anything an
 * author can see — SubmissionPresenter is the only serialiser allowed to touch it, and it
 * asks the policy first.
 */
class ReviewAssignment extends Model
{
    /** @use HasFactory<ReviewAssignmentFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => ReviewerStatus::class,
            'invited_at' => 'datetime',
            'due_at' => 'datetime',
            'responded_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_reminded_at' => 'datetime',
        ];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(ReviewRound::class, 'review_round_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function submission(): ?Submission
    {
        return $this->round?->submission;
    }

    /** The invitation is still live: the editor is waiting on this person. */
    public function isOutstanding(): bool
    {
        return $this->status->isOutstanding();
    }

    public function isOverdue(): bool
    {
        return $this->isOutstanding() && $this->due_at->isPast();
    }

    /** Reviews this user still owes someone — the "Reviews I owe" tab. */
    public function scopeOutstandingFor(Builder $query, User $reviewer): Builder
    {
        return $query->where('reviewer_id', $reviewer->id)
            ->whereIn('status', [ReviewerStatus::Invited, ReviewerStatus::Accepted]);
    }
}
