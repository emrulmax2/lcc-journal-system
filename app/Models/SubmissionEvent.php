<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SubmissionEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An append-only audit row. Write it with Submission::recordEvent(), never directly.
 *
 * UPDATED_AT is null because the table has no such column, on purpose: an audit row that
 * can be edited after the fact is not evidence of anything.
 */
class SubmissionEvent extends Model
{
    /** @use HasFactory<SubmissionEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /** NULL for events with no human actor — a scheduled reminder, an automatic escalation. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
