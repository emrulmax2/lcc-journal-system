<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReviewerStatus;
use Database\Factories\ReviewRoundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewRound extends Model
{
    /** @use HasFactory<ReviewRoundFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'round_number' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /** Ordered by id, which is also the order the anonymised "Reviewer 1/2/3" labels use. */
    public function assignments(): HasMany
    {
        return $this->hasMany(ReviewAssignment::class)->orderBy('id');
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /**
     * Every reviewer who accepted has now reported — the point at which the manuscript is
     * genuinely waiting on the EDITOR rather than on a reviewer. A round with no live
     * invitations at all is not "complete"; it has nobody in it.
     */
    public function allReportsIn(): bool
    {
        $live = $this->assignments()
            ->where('status', '!=', ReviewerStatus::Declined)
            ->count();

        if ($live === 0) {
            return false;
        }

        return $this->assignments()
            ->where('status', ReviewerStatus::ReportSubmitted)
            ->count() === $live;
    }
}
