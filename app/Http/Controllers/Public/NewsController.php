<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\NewsItem;
use App\Services\Content\MarkdownRenderer;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The news detail page that never existed.
 *
 * `news_items` has had `slug` (unique) and `body` (longText) since the first migration.
 * Six news cards render on the homepage, each with a "Read the story →" affordance, and
 * every one of them was `href="#"`. The body column was written for and read by nothing.
 */
class NewsController extends Controller
{
    public function index(): Response
    {
        $news = NewsItem::query()
            ->published()
            ->with(['media', 'author'])
            ->orderByDesc('published_at')
            ->paginate(12);

        return Inertia::render('News', [
            'news' => $news->through(fn (NewsItem $n) => $this->card($n)),
            'meta' => [
                'title' => 'News',
                'description' => 'Editorial announcements, calls for papers and research highlights.',
            ],
        ]);
    }

    public function show(NewsItem $news): Response
    {
        abort_unless($news->published_at !== null && $news->published_at->isPast(), 404);

        $news->load(['media', 'author']);

        $related = NewsItem::query()
            ->published()
            ->whereKeyNot($news->id)
            ->with('media')
            ->orderByDesc('published_at')
            ->limit(3)
            ->get()
            ->map(fn (NewsItem $n) => $this->card($n));

        return Inertia::render('NewsItem', [
            'item' => array_merge($this->card($news), [
                'bodyHtml' => app(MarkdownRenderer::class)->toHtml($news->body),
                'author' => $news->author?->fullName(),
            ]),
            'related' => $related,
            'meta' => [
                'title' => $news->title,
                'description' => $news->excerpt
                    ?: app(MarkdownRenderer::class)->toText($news->body),
            ],
        ])->withViewData(['canonical' => route('news.show', $news->slug)]);
    }

    /** @return array<string, mixed> */
    private function card(NewsItem $item): array
    {
        return [
            'slug' => $item->slug,
            'title' => $item->title,
            'category' => $item->category,
            'date' => $item->published_at?->toDateString(),
            'excerpt' => $item->excerpt,
            'url' => route('news.show', $item->slug),

            // media (a real asset we own) -> photo_key (Unsplash stock) -> nothing.
            // The frontend renders the placeholder rather than inventing an image.
            'image' => $item->media?->only(['url', 'alt', 'caption', 'credit']),
            'photo' => $item->photo_key,
        ];
    }
}
