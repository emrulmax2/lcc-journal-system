<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message in a discussion thread. APPEND-ONLY: UPDATED_AT is null because the table has
 * no such column, on purpose — an editorial record that can be edited after the fact is not
 * evidence of anything. Same reasoning as SubmissionEvent.
 */
class SubmissionDiscussionMessage extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function discussion(): BelongsTo
    {
        return $this->belongsTo(SubmissionDiscussion::class, 'discussion_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
