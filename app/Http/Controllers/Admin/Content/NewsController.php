<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Content;

use App\Http\Controllers\Controller;
use App\Models\NewsItem;
use App\Models\User;
use App\Services\Content\MediaLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * News items — the stories behind /news and /news/{slug}.
 *
 * THE SLUG IS THE URL, AND ONCE A STORY IS PUBLISHED SOMEBODY MAY HAVE LINKED TO IT.
 *
 * A news slug is not a DOI: it is not an identifier anyone has undertaken to keep resolving
 * forever, and a typo in one on the day of publication is worth fixing. So changing it is
 * ALLOWED — unlike an article's, which is frozen in an observer because the DOI depends on it.
 *
 * But it is not free, and the form says exactly what it costs: every existing link to the old
 * URL 404s, including the one in the newsletter that has already gone out. The editor decides;
 * they just do not get to decide by accident.
 *
 * published_at is the switch: NULL is a draft, a future date is scheduled (NewsItem::published()
 * is `whereNotNull AND <= now`), a past date is live.
 */
final class NewsController extends Controller
{
    public function index(): Response
    {
        $news = NewsItem::query()
            ->with(['media', 'author'])
            ->orderByRaw('published_at IS NULL DESC')   // drafts first: they are the work in hand
            ->orderByDesc('published_at')
            ->get();

        return Inertia::render('Admin/Content/News', [
            'news' => $news->map(fn (NewsItem $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'slug' => $item->slug,
                'category' => $item->category,
                'excerpt' => $item->excerpt,
                'publishedAt' => $item->published_at?->toIso8601String(),
                'isPublished' => $item->published_at !== null && $item->published_at->isPast(),
                'isScheduled' => $item->published_at !== null && $item->published_at->isFuture(),
                'author' => $item->author?->fullName(),
                'image' => $item->media?->only(['url', 'alt']),
                'url' => '/news/'.$item->slug,
            ])->values()->all(),

            'meta' => [
                'title' => 'News — content',
                'description' => 'Editorial announcements and research highlights.',
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Content/NewsEditor', array_merge($this->formData(), [
            'item' => null,
            'meta' => [
                'title' => 'New story — content',
                'description' => 'A new news item.',
            ],
        ]));
    }

    public function edit(NewsItem $news): Response
    {
        return Inertia::render('Admin/Content/NewsEditor', array_merge($this->formData(), [
            'item' => [
                'id' => $news->id,
                'title' => $news->title,
                'slug' => $news->slug,
                'category' => $news->category,
                'excerpt' => $news->excerpt,
                'body' => $news->body,
                'mediaId' => $news->media_id,
                'authorId' => $news->author_id,
                'publishedAt' => $news->published_at?->format('Y-m-d\TH:i'),
                'isPublished' => $news->published_at !== null && $news->published_at->isPast(),
                'url' => '/news/'.$news->slug,
            ],
            'meta' => [
                'title' => $news->title.' — content',
                'description' => 'Edit this story.',
            ],
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $item = NewsItem::create($this->validated($request, null));

        return redirect()
            ->route('admin.content.news.edit', $item)
            ->with('success', "“{$item->title}” created.");
    }

    public function update(Request $request, NewsItem $news): RedirectResponse
    {
        $news->update($this->validated($request, $news));

        return back()->with('success', "“{$news->title}” saved.");
    }

    public function destroy(NewsItem $news): RedirectResponse
    {
        $title = $news->title;
        $news->delete();

        return redirect()
            ->route('admin.content.news.index')
            ->with('success', "“{$title}” deleted.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?NewsItem $news): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('news_items', 'slug')->ignore($news?->id),
            ],
            'category' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'body' => ['nullable', 'string', 'max:200000'],
            'media_id' => ['nullable', 'integer', 'exists:media,id'],
            'author_id' => ['nullable', 'integer', 'exists:users,id'],
            'published_at' => ['nullable', 'date'],
        ], [
            'slug.regex' => 'A slug is lowercase letters, numbers and hyphens — it is the URL.',
        ]);

        $data['slug'] = Str::lower($data['slug']);

        return $data;
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return [
            'media' => app(MediaLibrary::class)->options(),

            'authors' => User::query()
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->fullName(),
                    'email' => $user->email,
                ])
                ->all(),

            'categories' => NewsItem::query()
                ->select('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->all(),
        ];
    }
}
