<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ResearchTopicFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ResearchTopic extends Model
{
    /** @use HasFactory<ResearchTopicFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'is_open' => 'boolean',
        ];
    }

    /** NULL for a cross-journal call for papers. */
    /** The real, self-hosted image. Falls back to photo_key (Unsplash) until one exists. */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function editors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'research_topic_editors')
            ->withTimestamps();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
