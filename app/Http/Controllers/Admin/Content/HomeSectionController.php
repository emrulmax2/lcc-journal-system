<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Content;

use App\Http\Controllers\Controller;
use App\Models\HomeSection;
use App\Models\HomeSectionItem;
use App\Services\Content\MediaLibrary;
use App\Support\PublicRoutes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The homepage's editorial bands: their eyebrow, heading, blurb, image, order and visibility,
 * and the cards inside them.
 *
 * `meta` ON A HOW-IT-WORKS STEP IS THE FIELD THIS SCREEN EXISTS FOR.
 *
 * The prototype rendered four medians in those steps — "About 20 minutes", "Median 4 days",
 * "Median 38 days", "Median 9 days" — as this platform's measured performance. They were not
 * computed from anything. They were typed. They also contradicted the "Median 51 days" in the
 * navbar, which was also typed. The seeder ships those steps with `meta` NULL, and this form
 * carries the warning, because the next person to fill the field in should have to read it:
 *
 *     "Only put a figure here that you can point at a source for."
 *
 * A real median exists (journal_metrics.median_days_to_decision) and is shown on the Journals
 * page, where it is attributable to a specific journal. This field is not that.
 *
 * The icon is an ALLOW-LIST (HomeSectionItem::ICONS), not a free string: the frontend resolves
 * the name against a static import map, so an unknown name renders NOTHING — an invisible card
 * with a hole in it, and no error anywhere.
 */
final class HomeSectionController extends Controller
{
    public function index(): Response
    {
        $sections = HomeSection::query()
            ->with(['items', 'media'])
            ->orderBy('sequence')
            ->get();

        return Inertia::render('Admin/Content/HomeSections', [
            'sections' => $sections->map(fn (HomeSection $section): array => [
                'id' => $section->id,
                'key' => $section->key,
                'name' => $section->name,
                'eyebrow' => $section->eyebrow,
                'heading' => $section->heading,
                'blurb' => $section->blurb,
                'mediaId' => $section->media_id,
                'isVisible' => $section->is_visible,
                'sequence' => $section->sequence,
                'items' => $section->items->map(fn (HomeSectionItem $item): array => [
                    'id' => $item->id,
                    'icon' => $item->icon,
                    'title' => $item->title,
                    'body' => $item->body,
                    'meta' => $item->meta,
                    'ctaLabel' => $item->cta_label,
                    'routeName' => $item->route_name,
                    'externalUrl' => $item->external_url,
                    'url' => $item->url(),
                ])->values()->all(),
            ])->values()->all(),

            'icons' => HomeSectionItem::ICONS,
            'routes' => PublicRoutes::options(),
            'media' => app(MediaLibrary::class)->options(),

            'meta' => [
                'title' => 'Homepage — content',
                'description' => 'The editorial bands on the homepage, and the cards inside them.',
            ],
        ]);
    }

    /**
     * One section and its items, in a single save.
     *
     * Items are created / updated / deleted THROUGH THE MODEL, one at a time. A mass upsert
     * would be fewer queries and would skip every model event this system relies on.
     */
    public function update(Request $request, HomeSection $section): RedirectResponse
    {
        $data = $request->validate([
            'eyebrow' => ['nullable', 'string', 'max:255'],
            'heading' => ['nullable', 'string', 'max:255'],
            'blurb' => ['nullable', 'string', 'max:2000'],
            'media_id' => ['nullable', 'integer', 'exists:media,id'],
            'is_visible' => ['boolean'],
            'sequence' => ['integer', 'min:0', 'max:999'],

            'items' => ['array'],
            'items.*.id' => [
                'nullable',
                'integer',
                Rule::exists('home_section_items', 'id')->where('home_section_id', $section->id),
            ],
            'items.*.icon' => ['nullable', Rule::in(HomeSectionItem::ICONS)],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.body' => ['nullable', 'string', 'max:2000'],

            // Free text, and OWNED by whoever types it. See the class docblock.
            'items.*.meta' => ['nullable', 'string', 'max:255'],

            'items.*.cta_label' => ['nullable', 'string', 'max:255'],
            'items.*.route_name' => ['nullable', 'string', Rule::in(PublicRoutes::names())],
            'items.*.external_url' => ['nullable', 'url:http,https', 'max:2048'],
        ], [
            'items.*.icon.in' => 'That icon is not in the allow-list. An unknown icon name renders '
                .'nothing at all — a card with a hole in it, and no error anywhere.',
            'items.*.route_name.in' => 'That route does not exist, or it takes a parameter.',
        ]);

        // HomeSectionItem::url() checks route_name FIRST and falls through to external_url. An
        // item carrying both would quietly ignore one of them — the same ambiguity MenuItem
        // refuses outright.
        foreach ($data['items'] ?? [] as $index => $item) {
            if (filled($item['route_name'] ?? null) && filled($item['external_url'] ?? null)) {
                throw ValidationException::withMessages([
                    "items.{$index}.external_url" => 'A card links to a route OR an external URL, not both. '
                        .'With both set, the external URL is silently ignored.',
                ]);
            }
        }

        DB::transaction(function () use ($section, $data): void {
            $section->update([
                'eyebrow' => $data['eyebrow'] ?? null,
                'heading' => $data['heading'] ?? null,
                'blurb' => $data['blurb'] ?? null,
                'media_id' => $data['media_id'] ?? null,
                'is_visible' => $data['is_visible'] ?? true,
                'sequence' => $data['sequence'] ?? $section->sequence,
            ]);

            $kept = [];

            foreach (array_values($data['items'] ?? []) as $index => $row) {
                $attributes = [
                    'icon' => $row['icon'] ?? null,
                    'title' => $row['title'],
                    'body' => $row['body'] ?? null,
                    'meta' => $row['meta'] ?? null,
                    'cta_label' => $row['cta_label'] ?? null,
                    'route_name' => $row['route_name'] ?? null,
                    'external_url' => $row['external_url'] ?? null,
                    'sequence' => $index,
                ];

                $item = filled($row['id'] ?? null)
                    ? $section->items()->whereKey($row['id'])->first()
                    : null;

                if ($item !== null) {
                    $item->update($attributes);
                } else {
                    $item = $section->items()->create($attributes);
                }

                $kept[] = $item->id;
            }

            // Removed in the form = deleted. Through the model, one at a time.
            $section->items()->whereKeyNot($kept)->get()->each->delete();
        });

        return back()->with('success', "“{$section->name}” saved.");
    }
}
