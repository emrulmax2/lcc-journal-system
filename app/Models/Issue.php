<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IssueStatus;
use Database\Factories\IssueFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issue extends Model
{
    /** @use HasFactory<IssueFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => IssueStatus::class,

            // NULL until published. A date cast, not datetime: an issue is dated to a
            // day, and a time-of-day here would be invented precision in the deposit.
            'publication_date' => 'date',
        ];
    }

    // --- Relations ----------------------------------------------------------

    public function volume(): BelongsTo
    {
        return $this->belongsTo(Volume::class);
    }

    /** Order is meaningful — it is the running order of the issue, not a sort. */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class)->orderBy('sequence');
    }

    /**
     * The owning journal, reached through the volume. An accessor rather than a
     * relation: the FK chain runs upward (issues -> volumes -> journals), which no
     * Eloquent has-through relation models. It cannot be eager-loaded on its own —
     * load `volume.journal` and this reads from that.
     */
    public function getJournalAttribute(): ?Journal
    {
        return $this->volume?->journal;
    }

    // --- State --------------------------------------------------------------

    public function isPublished(): bool
    {
        return $this->status === IssueStatus::Published;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', IssueStatus::Published);
    }
}
