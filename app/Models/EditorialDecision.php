<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DecisionType;
use Database\Factories\EditorialDecisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EditorialDecision extends Model
{
    /** @use HasFactory<EditorialDecisionFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'decision' => DecisionType::class,
            'decided_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /** NULL for a desk rejection — a decision taken before any round was opened. */
    public function round(): BelongsTo
    {
        return $this->belongsTo(ReviewRound::class, 'review_round_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_id');
    }
}
