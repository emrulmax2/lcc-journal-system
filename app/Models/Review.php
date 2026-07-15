<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Recommendation;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reviewer's report.
 *
 * `comments_to_editor` is CONFIDENTIAL and must never appear in an author-facing response.
 * It is $hidden as a second line of defence: the presenter layer already refuses to emit
 * it, but a stray ->toArray() in a log line, a debug dump or a future endpoint must not
 * leak it either. The author-facing Dashboard shows a reviewer's STATUS and
 * RECOMMENDATION and nothing else about them.
 */
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['comments_to_editor'];

    protected function casts(): array
    {
        return [
            'recommendation' => Recommendation::class,
            'submitted_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ReviewAssignment::class, 'review_assignment_id');
    }
}
