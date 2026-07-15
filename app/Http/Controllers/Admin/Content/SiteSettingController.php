<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Content;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\Content\MediaLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The site's chrome, as editable rows: brand, hero, footer, contact, social.
 *
 * The form is DRIVEN BY THE DATA, not by a hand-written field list. Each row carries its
 * own `type`, `label` and `help`, so adding an editable string is a seeder line rather than
 * a migration + a controller change + a component change — which is the whole reason this
 * is a key/value table (see the site_settings migration).
 *
 * The `type` is also the VALIDATION. A row typed `url` is validated as a URL and a row typed
 * `email` as an email, on the server, whatever the browser did or did not enforce.
 *
 * Saving goes through the model, one row at a time, on purpose: SiteSetting::booted() flushes
 * the SiteContent cache on save, and a mass UPDATE would bypass that and leave every page on
 * the site rendering the old footer until the cache expired an hour later. Do not "optimise"
 * this into an upsert.
 */
final class SiteSettingController extends Controller
{
    public function edit(): Response
    {
        $settings = SiteSetting::query()
            ->orderBy('group')
            ->orderBy('sequence')
            ->get();

        return Inertia::render('Admin/Content/Settings', [
            'groups' => $settings
                ->groupBy('group')
                ->map(fn ($rows, string $group): array => [
                    'key' => $group,
                    'label' => self::GROUP_LABELS[$group] ?? ucfirst($group),
                    'description' => self::GROUP_DESCRIPTIONS[$group] ?? null,
                    'settings' => $rows->map(fn (SiteSetting $setting): array => [
                        'key' => $setting->key,
                        'type' => $setting->type,
                        'label' => $setting->label,
                        'help' => $setting->help,
                    ])->values()->all(),
                ])
                ->values()
                ->all(),

            // Keyed by setting key. A `media` row's value is a media id; a `boolean` row's
            // is '1' or '0' — both are sent as-is and the component knows from `type`.
            'values' => $settings->mapWithKeys(fn (SiteSetting $s) => [$s->key => $s->value])->all(),

            'media' => app(MediaLibrary::class)->options(),

            'meta' => [
                'title' => 'Site settings — content',
                'description' => 'Brand, hero, footer, contact and social links.',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $settings = SiteSetting::all()->keyBy('key');

        $rules = ['values' => ['required', 'array']];
        $attributes = [];

        foreach ($settings as $key => $setting) {
            $rules["values.{$key}"] = $this->rulesFor($setting);
            $attributes["values.{$key}"] = $setting->label;
        }

        $data = $request->validate($rules, [], $attributes);

        foreach ($data['values'] as $key => $value) {
            $setting = $settings->get($key);

            if ($setting === null) {
                continue;   // a key that is not a row is not a setting
            }

            // One save per row, through the model, so the cache flush actually happens.
            $setting->update(['value' => $this->normalise($setting, $value)]);
        }

        return back()->with('success', 'Site settings saved. The change is live now — the '
            .'navbar and footer are not cached until the next deploy.');
    }

    /** @return list<mixed> */
    private function rulesFor(SiteSetting $setting): array
    {
        return match ($setting->type) {
            'url' => ['nullable', 'url:http,https', 'max:2048'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'media' => ['nullable', 'integer', 'exists:media,id'],
            'boolean' => ['nullable', 'boolean'],
            'textarea', 'markdown' => ['nullable', 'string', 'max:20000'],
            default => ['nullable', 'string', 'max:500'],
        };
    }

    private function normalise(SiteSetting $setting, mixed $value): ?string
    {
        if ($setting->type === 'boolean') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
        }

        if ($value === null || $value === '') {
            // '' and NULL are the same thing for a setting: unset. The footer hides a social
            // icon whose URL is blank rather than linking it to "#".
            return null;
        }

        return (string) $value;
    }

    /** @var array<string, string> */
    private const GROUP_LABELS = [
        'general' => 'General',
        'hero' => 'Homepage hero',
        'footer' => 'Footer & newsletter',
        'contact' => 'Contact',
        'social' => 'Social links',
    ];

    /** @var array<string, string> */
    private const GROUP_DESCRIPTIONS = [
        'general' => 'The site name and wordmark. These appear on every page.',
        'hero' => 'The band at the top of the homepage. Every claim made here is a claim the site is making on launch day.',
        'footer' => 'The footer copy and the newsletter signup. Read the notes — two of these have burnt us before.',
        'contact' => 'Where an author or a reader is told to write.',
        'social' => 'Left empty, the icon is hidden. An icon that links to “#” looks like a live account and goes nowhere.',
    ];
}
