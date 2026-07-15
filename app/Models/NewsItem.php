<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NewsItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsItem extends Model
{
    /** @use HasFactory<NewsItemFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /** The real, self-hosted image. Falls back to photo_key (Unsplash) until one exists. */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** A future published_at is a scheduled post, not a live one. NULL is a draft. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
