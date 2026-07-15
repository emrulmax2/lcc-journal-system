<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Content;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\Content\MediaLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Content pages: author guidelines, publication ethics, APCs, privacy, accessibility.
 *
 * THE BODY IS MARKDOWN AND IS RENDERED SERVER-SIDE. There is no WYSIWYG and no stored HTML —
 * see MarkdownRenderer for why that is a security decision and not a UX one. The editor gets
 * a toolbar that inserts markdown and a preview that round-trips through the server's own
 * renderer, so the preview cannot drift from the page.
 *
 * A SYSTEM PAGE CANNOT BE DELETED. The footer and navbar link to it structurally; deleting it
 * does not remove the link, it turns the link into a 404 that nothing on the site would ever
 * report. Page::booted() throws, and this controller refuses before that with a 403 — the UI
 * must never offer an action the model will refuse.
 */
final class PageController extends Controller
{
    public function index(): Response
    {
        $pages = Page::query()
            ->with('heroMedia')
            ->orderBy('title')
            ->get();

        return Inertia::render('Admin/Content/Pages', [
            'pages' => $pages->map(fn (Page $page): array => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'summary' => $page->summary,
                'status' => $page->status,
                'publishedAt' => $page->published_at?->toIso8601String(),
                'isPublished' => $page->isPublished(),
                'isSystem' => $page->is_system,
                'url' => '/'.$page->slug,
                'updatedAt' => $page->updated_at?->toIso8601String(),
            ])->values()->all(),

            'meta' => [
                'title' => 'Pages — content',
                'description' => 'Author guidelines, policies, legal pages.',
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Content/PageEditor', [
            'page' => null,
            'media' => app(MediaLibrary::class)->options(),
            'reservedSlugs' => $this->reservedSlugs(),
            'meta' => [
                'title' => 'New page — content',
                'description' => 'A new content page.',
            ],
        ]);
    }

    public function edit(Page $page): Response
    {
        $page->load('heroMedia');

        return Inertia::render('Admin/Content/PageEditor', [
            'page' => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'summary' => $page->summary,
                'body' => $page->body,
                'heroMediaId' => $page->hero_media_id,
                'status' => $page->status,
                // datetime-local wants "Y-m-d\TH:i" and nothing else.
                'publishedAt' => $page->published_at?->format('Y-m-d\TH:i'),
                'metaTitle' => $page->meta_title,
                'metaDescription' => $page->meta_description,
                'isSystem' => $page->is_system,
                'isPublished' => $page->isPublished(),
                'url' => '/'.$page->slug,
            ],
            'media' => app(MediaLibrary::class)->options(),
            'reservedSlugs' => $this->reservedSlugs(),
            'meta' => [
                'title' => $page->title.' — content',
                'description' => 'Edit this page.',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);

        $page = Page::create([...$data, 'is_system' => false, 'updated_by' => $request->user()->id]);

        return redirect()
            ->route('admin.content.pages.edit', $page)
            ->with('success', "“{$page->title}” created.");
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $data = $this->validated($request, $page);

        // is_system is NOT in $data and is never settable from the form. Whether the site's
        // navigation structurally depends on a page is a fact about the site, not a checkbox.
        $page->update([...$data, 'updated_by' => $request->user()->id]);

        return back()->with('success', "“{$page->title}” saved.");
    }

    public function destroy(Page $page): RedirectResponse
    {
        // Page::booted() throws a RuntimeException on this, which would be a 500. The refusal
        // belongs here, as a 403 with a reason — and the UI does not render the button at all.
        abort_if(
            $page->is_system,
            403,
            "“{$page->title}” is a system page — the site navigation links to it, and deleting it "
                .'would turn that link into a 404. Unpublish it instead.',
        );

        $title = $page->title;
        $page->delete();

        return redirect()
            ->route('admin.content.pages.index')
            ->with('success', "“{$title}” deleted.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Page $page): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],

            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('pages', 'slug')->ignore($page?->id),

                // A page lives at /{slug}, and that route is declared LAST so it does not
                // swallow /journals or /admin. The flip side: a page slugged "journals" is
                // matched by the journals route first and is simply unreachable, with no
                // error anywhere. Refuse it at the point someone types it.
                Rule::notIn($this->reservedSlugs()),
            ],

            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:200000'],
            'hero_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'status' => ['required', Rule::in(['draft', 'published'])],

            /*
             * BOTH are required to publish. Page::scopePublished() and Page::isPublished()
             * ask for status = published AND a published_at that is in the past — so a page
             * marked published with no date is not on the site, and nothing would say why.
             */
            'published_at' => ['nullable', 'date', Rule::requiredIf($request->input('status') === 'published')],

            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ], [
            'slug.regex' => 'A slug is lowercase letters, numbers and hyphens — it is the URL.',
            'slug.not_in' => 'That slug is already a route on this site (e.g. /journals, /articles, /admin). '
                .'The page would be unreachable, because the other route matches first.',
            'published_at.required' => 'A published page needs a publication date. Without one it is not '
                .'live — status alone does not put it on the site.',
        ]);

        $data['slug'] = Str::lower($data['slug']);

        return $data;
    }

    /**
     * The first path segment of every non-catch-all route. A page slugged with one of these
     * can never be reached.
     *
     * @return list<string>
     */
    private function reservedSlugs(): array
    {
        $reserved = ['storage', 'build', 'vendor'];

        foreach (RouteFacade::getRoutes() as $route) {
            $uri = trim($route->uri(), '/');

            if ($uri === '' || str_starts_with($uri, '{')) {
                continue;   // '/' and the /{page} catch-all itself
            }

            $reserved[] = explode('/', $uri)[0];
        }

        return array_values(array_unique(array_filter($reserved, fn (string $s): bool => ! str_contains($s, '{'))));
    }
}
