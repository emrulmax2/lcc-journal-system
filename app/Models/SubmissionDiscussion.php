<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubmissionStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An internal editorial discussion thread on one manuscript. Never author-facing.
 */
class SubmissionDiscussion extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'stage' => SubmissionStage::class,
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SubmissionDiscussionMessage::class, 'discussion_id')->orderBy('created_at');
    }

    public function participants(): BelongsToMany
    {
        // No withTimestamps(): the pivot has created_at only (DB useCurrent fills it) and no
        // updated_at for Eloquent to manage.
        return $this->belongsToMany(User::class, 'submission_discussion_participants', 'discussion_id', 'user_id')
            ->withPivot('added_by');
    }
}
