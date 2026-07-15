<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Content\SiteContent;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'opens_in_new_tab' => 'boolean',
            'is_active' => 'boolean',
            'sequence' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        /**
         * A menu item points at EXACTLY ONE destination.
         *
         * Enforced here rather than only in a form request, because the failure it
         * prevents is silent: an item with both a page_id and an external_url resolves to
         * whichever the accessor happens to check first, and the editor sees a link that
         * goes somewhere they did not choose. That is how "Careers" ends up on the
         * homepage.
         */
        static::saving(function (MenuItem $item): void {
            $destinations = collect([$item->page_id, $item->route_name, $item->external_url])
                ->filter(fn ($v) => filled($v))
                ->count();

            if ($destinations !== 1) {
                throw new InvalidArgumentException(
                    "Menu item '{$item->label}' must point at exactly one of: a page, a named route, "
                    ."or an external URL. It currently has {$destinations}."
                );
            }

            // A named route that does not exist is a 404 the moment someone clicks it,
            // and nothing else in the system would ever tell us.
            if (filled($item->route_name) && ! Route::has($item->route_name)) {
                throw new InvalidArgumentException(
                    "Menu item '{$item->label}' points at route '{$item->route_name}', which does not exist."
                );
            }
        });

        static::saved(fn () => SiteContent::flush());
        static::deleted(fn () => SiteContent::flush());
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sequence');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /** Resolves whichever of the three destinations is set. Never returns "#". */
    public function url(): string
    {
        if ($this->page_id !== null) {
            return route('pages.show', $this->page?->slug ?? '');
        }

        if (filled($this->route_name)) {
            return route($this->route_name);
        }

        return (string) $this->external_url;
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'description' => $this->description,
            'url' => $this->url(),
            'external' => filled($this->external_url),
            'newTab' => $this->opens_in_new_tab,
            'children' => $this->relationLoaded('children')
                ? $this->children->map(fn (self $c) => $c->toPayload())->values()->all()
                : [],
        ];
    }
}
