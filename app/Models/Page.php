<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Content\MarkdownRenderer;
use App\Services\Content\SiteContent;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_system' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        /**
         * A system page is one the footer or navbar structurally depends on — the privacy
         * policy, the accessibility statement, the author guidelines. Deleting one does
         * not remove a link; it turns that link into a 404 that nobody notices, because
         * nothing on the site links back to check.
         *
         * The admin hides the delete control for these. This is the layer that means it
         * cannot happen anyway.
         */
        static::deleting(function (Page $page): void {
            if ($page->is_system) {
                throw new RuntimeException(
                    "'{$page->title}' is a system page — the site navigation links to it, and deleting "
                    .'it would turn that link into a 404. Unpublish it instead.'
                );
            }
        });

        /**
         * A page change invalidates the CACHED MENUS, not just the page.
         *
         * SiteContent caches every menu item's RESOLVED url, and a page-backed item resolves
         * through this model's slug. So renaming a slug without flushing leaves the footer
         * pointing at the old URL — which now 404s — for as long as the cache lives, and the
         * editor sees their page working while every reader gets a dead link.
         *
         * Same shape as SiteSetting::booted() and MenuItem::booted(): the model flushes, not
         * whoever remembers to.
         */
        static::saved(fn () => SiteContent::flush());
        static::deleted(fn () => SiteContent::flush());
    }

    public function heroMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'hero_media_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Markdown -> HTML, with raw HTML escaped. See MarkdownRenderer. */
    public function bodyHtml(): string
    {
        return app(MarkdownRenderer::class)->toHtml($this->body);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->status === 'published'
            && $this->published_at !== null
            && $this->published_at->isPast();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
