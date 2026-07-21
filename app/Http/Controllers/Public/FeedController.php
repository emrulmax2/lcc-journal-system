<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Journal;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * Atom syndication feeds — the site's latest articles, and each journal's.
 *
 * Server-rendered XML, like the sitemap and OAI: feed readers and aggregators do not run
 * JavaScript. A feed is how readers and indexers follow new work without polling the site,
 * and OJS journals are expected to publish one.
 */
class FeedController extends Controller
{
    private const LIMIT = 50;

    public function site(): Response
    {
        $articles = $this->latest();

        return $this->atom(
            id: route('home'),
            title: config('app.name').' — latest articles',
            self: route('feed'),
            articles: $articles,
        );
    }

    public function journal(Journal $journal): Response
    {
        abort_unless((bool) $journal->is_active, 404);

        $articles = $this->latest($journal);

        return $this->atom(
            id: route('journals.show', $journal->slug),
            title: $journal->title.' — latest articles',
            self: route('journals.feed', $journal->slug),
            articles: $articles,
        );
    }

    /** @return Collection<int, Article> */
    private function latest(?Journal $journal = null)
    {
        return Article::query()
            ->published()
            ->when($journal !== null, fn ($q) => $q->where('journal_id', $journal->id))
            ->with(['journal', 'authors'])
            ->orderByDesc('published_at')
            ->limit(self::LIMIT)
            ->get();
    }

    /** @param  Collection<int, Article>  $articles */
    private function atom(string $id, string $title, string $self, $articles): Response
    {
        // The feed's own <updated> is the newest article's date — a feed that claims to have
        // changed when it has not wastes every reader's conditional GET.
        $updated = $articles->first()?->published_at ?? now();

        $xml = view('feeds.atom', [
            'feedId' => $id,
            'feedTitle' => $title,
            'selfUrl' => $self,
            'updated' => $updated,
            'articles' => $articles,
        ])->render();

        return response($xml, 200, ['Content-Type' => 'application/atom+xml; charset=utf-8']);
    }
}
