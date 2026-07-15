<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Content;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Support\PublicRoutes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Navigation, as data. The navbar, the authors mega-menu, the three footer columns and the
 * legal strip.
 *
 * AN ITEM POINTS AT EXACTLY ONE DESTINATION: a page, a named route, or an external URL.
 *
 * That rule is enforced in THREE places, and all three are load-bearing:
 *
 *   1. The form makes an invalid combination unsubmittable — it is a radio, so choosing
 *      "page" clears the other two. An editor cannot construct the broken state.
 *   2. This controller validates it anyway, because a form is a courtesy and a POST is a
 *      POST.
 *   3. MenuItem::saving() throws, because the failure it prevents is SILENT: an item with
 *      both a page_id and an external_url resolves to whichever the accessor checks first,
 *      and the editor sees a link that goes somewhere they did not choose.
 *
 * That third layer throws an InvalidArgumentException, which is a 500 if nobody catches it.
 * So it is caught here and returned as a validation error — the editor gets a message on the
 * field, not a stack trace.
 *
 * THE RESOLVED URL IS SHOWN FOR EVERY ITEM. The bug this whole table exists to kill was
 * seventeen hardcoded footer links, nine of which went to "#" or the homepage. An editor
 * must be able to SEE where a link actually goes, at the moment they save it.
 */
final class MenuController extends Controller
{
    public function index(): Response
    {
        $menus = Menu::query()
            ->with(['items' => fn ($q) => $q->orderBy('sequence'), 'items.page'])
            ->orderBy('sequence')
            ->get();

        return Inertia::render('Admin/Content/Menus', [
            'menus' => $menus->map(fn (Menu $menu): array => [
                'id' => $menu->id,
                'key' => $menu->key,
                'name' => $menu->name,
                'location' => $menu->location,
                'items' => $menu->items->map(fn (MenuItem $item): array => $this->item($item))->values()->all(),
            ])->values()->all(),

            // Only PUBLISHED pages are offerable. Pointing the footer at a draft is pointing
            // it at a 404 — the public page controller refuses one to anybody but a site admin.
            'pages' => Page::query()
                ->published()
                ->orderBy('title')
                ->get(['id', 'title', 'slug'])
                ->map(fn (Page $page): array => [
                    'id' => $page->id,
                    'title' => $page->title,
                    'url' => '/'.$page->slug,
                ])
                ->all(),

            'routes' => PublicRoutes::options(),

            'meta' => [
                'title' => 'Navigation — content',
                'description' => 'The navbar, mega-menu and footer links.',
            ],
        ]);
    }

    public function storeItem(Request $request, Menu $menu): RedirectResponse
    {
        $data = $this->validated($request, $menu, null);

        // whereNull, not `where(..., null)` — `parent_id = NULL` is never true in SQL, and a
        // top-level item would silently get sequence 1 forever, stacking every new item on
        // top of the first one.
        $siblings = MenuItem::where('menu_id', $menu->id);

        $data['parent_id'] === null
            ? $siblings->whereNull('parent_id')
            : $siblings->where('parent_id', $data['parent_id']);

        $data['sequence'] = (int) $siblings->max('sequence') + 1;

        $this->guardingModelRules(function () use ($menu, $data): void {
            $menu->items()->create($data);
        });

        return back()->with('success', "“{$data['label']}” added to {$menu->name}.");
    }

    public function updateItem(Request $request, MenuItem $item): RedirectResponse
    {
        $data = $this->validated($request, $item->menu, $item);

        $this->guardingModelRules(function () use ($item, $data): void {
            $item->update($data);
        });

        return back()->with('success', "“{$data['label']}” saved.");
    }

    public function destroyItem(MenuItem $item): RedirectResponse
    {
        $label = $item->label;
        $item->delete();

        return back()->with('success', "“{$label}” removed.");
    }

    /**
     * The running order of one sibling group.
     *
     * Each row is saved through the model rather than mass-updated, because MenuItem::saved()
     * is what flushes the SiteContent cache — a mass UPDATE would reorder the database and
     * leave every visitor looking at the old menu until the cache expired.
     */
    public function reorder(Request $request, Menu $menu): RedirectResponse
    {
        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer', Rule::exists('menu_items', 'id')->where('menu_id', $menu->id)],
        ]);

        $items = MenuItem::query()
            ->whereIn('id', $data['order'])
            ->where('menu_id', $menu->id)
            ->get()
            ->keyBy('id');

        DB::transaction(function () use ($data, $items): void {
            foreach (array_values($data['order']) as $index => $id) {
                $item = $items->get($id);

                if ($item === null || $item->sequence === $index) {
                    continue;
                }

                $item->update(['sequence' => $index]);
            }
        });

        return back()->with('success', 'Order saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Menu $menu, ?MenuItem $item): array
    {
        $parentRules = [
            'nullable',
            'integer',
            Rule::exists('menu_items', 'id')->where('menu_id', $menu->id),
        ];

        if ($item !== null) {
            $parentRules[] = Rule::notIn([$item->id]);   // an item cannot be its own parent
        }

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],

            'page_id' => ['nullable', 'integer', Rule::exists('pages', 'id')],

            // The ALLOW-LIST, built from the router. A name outside it is either a route that
            // does not exist (MenuItem::saving() would throw) or one that takes a parameter
            // (route() would throw), and both are a 500 on a public page.
            'route_name' => ['nullable', 'string', Rule::in(PublicRoutes::names())],

            'external_url' => ['nullable', 'url:http,https', 'max:2048'],

            'parent_id' => $parentRules,

            'opens_in_new_tab' => ['boolean'],
            'is_active' => ['boolean'],
        ], [
            'route_name.in' => 'That route does not exist, or it takes a parameter and cannot be '
                .'linked from a menu. Choose one from the list, or link a page instead.',
            'parent_id.not_in' => 'An item cannot be nested under itself.',
        ]);

        $destinations = collect([$data['page_id'] ?? null, $data['route_name'] ?? null, $data['external_url'] ?? null])
            ->filter(fn ($value): bool => filled($value))
            ->count();

        if ($destinations !== 1) {
            throw ValidationException::withMessages([
                'destination' => $destinations === 0
                    ? 'This item points nowhere. Choose a page, a route, or an external URL.'
                    : 'This item points at '.$destinations.' places at once. It must point at exactly one — '
                        .'otherwise the link resolves to whichever is checked first, and that is not a choice '
                        .'anybody made.',
            ]);
        }

        return [
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
            'page_id' => $data['page_id'] ?? null,
            'route_name' => $data['route_name'] ?? null,
            'external_url' => $data['external_url'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'opens_in_new_tab' => $data['opens_in_new_tab'] ?? false,
            'is_active' => $data['is_active'] ?? true,
        ];
    }

    /**
     * MenuItem::saving() throws InvalidArgumentException. Uncaught, that is a 500 and a stack
     * trace where a message on a field belongs. The validation above should mean it never
     * fires — this is the backstop that guarantees it, whatever a future edit does.
     */
    private function guardingModelRules(callable $save): void
    {
        try {
            $save();
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['destination' => $e->getMessage()]);
        }
    }

    /** @return array<string, mixed> */
    private function item(MenuItem $item): array
    {
        $destination = match (true) {
            $item->page_id !== null => 'page',
            filled($item->route_name) => 'route',
            default => 'external',
        };

        return [
            'id' => $item->id,
            'label' => $item->label,
            'description' => $item->description,
            'destination' => $destination,
            'pageId' => $item->page_id,
            'routeName' => $item->route_name,
            'externalUrl' => $item->external_url,
            'parentId' => $item->parent_id,
            'opensInNewTab' => $item->opens_in_new_tab,
            'isActive' => $item->is_active,
            'sequence' => $item->sequence,

            // WHERE IT ACTUALLY GOES. Not "#".
            'url' => $item->url(),

            // A live link into a draft page is a 404 for every reader. Say so here rather than
            // let a visitor find it.
            'pointsAtDraft' => $item->page_id !== null && ! ($item->page?->isPublished() ?? false),
        ];
    }
}
