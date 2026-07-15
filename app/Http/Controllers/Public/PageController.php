<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\Content\MarkdownRenderer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function show(Request $request, Page $page): Response
    {
        // A draft page is not public. Editors preview it, and the preview is noindex — a
        // half-written APC policy getting indexed and quoted back at us is a real problem.
        $isPreview = ! $page->isPublished();

        if ($isPreview) {
            abort_unless($request->user()?->is_site_admin ?? false, 404);
        }

        $page->load('heroMedia');

        return Inertia::render('Page', [
            'page' => [
                'slug' => $page->slug,
                'title' => $page->title,
                'summary' => $page->summary,
                // Rendered SERVER-SIDE from markdown, with raw HTML escaped. The frontend
                // receives finished, safe HTML and never parses markdown itself — one
                // renderer, one set of rules, no second implementation to drift.
                'bodyHtml' => $page->bodyHtml(),
                'updatedAt' => $page->updated_at?->toDateString(),
                'heroImage' => $page->heroMedia?->only(['url', 'alt', 'caption', 'credit']),
                'isPreview' => $isPreview,
            ],
            'meta' => [
                'title' => $page->meta_title ?: $page->title,
                'description' => $page->meta_description
                    ?: ($page->summary ?: app(MarkdownRenderer::class)->toText($page->body)),
            ],
        ])->withViewData(['indexable' => ! $isPreview]);
    }
}
