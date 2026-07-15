<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Models\Media;
use App\Models\Menu;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves the site-wide content that appears on EVERY page: brand, navigation, footer.
 *
 * Cached, because this is shared into every Inertia response — including the SSR render —
 * and it must not cost three queries per request forever. The cache is flushed by the
 * models themselves on save (see SiteSetting::booted / Menu::booted), not by whoever
 * remembers to call it: a stale navbar that only clears on a deploy is exactly the kind
 * of "the CMS doesn't work" that makes editors stop using it and go back to asking a
 * developer.
 */
final class SiteContent
{
    private const CACHE_KEY = 'site-content';

    private const TTL = 3600;

    /** @return array<string, mixed> */
    public function shared(): array
    {
        return Cache::remember(self::CACHE_KEY, self::TTL, function (): array {
            return [
                'settings' => $this->settings(),
                'menus' => $this->menus(),
            ];
        });
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        $media = [];
        $values = [];

        foreach (SiteSetting::all() as $setting) {
            $values[$setting->key] = $setting->value;

            // A `media` setting stores an id. Resolving it here means the frontend never
            // has to know that, and never has to build a URL itself.
            if ($setting->type === 'media' && filled($setting->value)) {
                $media[$setting->key] = Media::find($setting->value)?->toArray();
            }
        }

        foreach ($media as $key => $resolved) {
            $values[$key] = $resolved;
        }

        return $values;
    }

    /** @return array<string, list<array<string, mixed>>> keyed by menu key */
    private function menus(): array
    {
        return Menu::with(['items' => fn ($q) => $q->whereNull('parent_id')->where('is_active', true)->orderBy('sequence'),
            'items.children' => fn ($q) => $q->where('is_active', true)->orderBy('sequence'),
            'items.page', 'items.children.page'])
            ->orderBy('sequence')
            ->get()
            ->mapWithKeys(fn (Menu $menu) => [
                $menu->key => [
                    'name' => $menu->name,
                    'location' => $menu->location,
                    'items' => $menu->items->map(fn ($item) => $item->toPayload())->values()->all(),
                ],
            ])
            ->all();
    }
}
