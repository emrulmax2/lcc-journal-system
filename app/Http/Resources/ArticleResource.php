<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * The shape the React pages expect. Derived from the frontend contract in
 * resources/js/lib/data.ts — field names match the TypeScript `Article` type exactly so
 * the page components need no rewiring beyond changing where the data comes from.
 *
 * `authors` is a flat string[] in the card contexts, because that is what ArticleCard
 * renders (`authors.join(', ')`). The detail page needs the full objects, so it asks for
 * them via ArticleDetailResource.
 *
 * @mixin Article
 */
class ArticleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'authors' => $this->displayAuthors(),
            'journal' => $this->whenLoaded('journal', fn () => $this->journal->title),
            'journalSlug' => $this->whenLoaded('journal', fn () => $this->journal->slug),
            'type' => $this->whenLoaded('section', fn () => $this->section?->name),
            'date' => $this->published_at?->toDateString(),
            // Which printed issue — "Vol 5, No 1". Only where the caller loaded issue.volume.
            'printIssue' => $this->whenLoaded('issue', fn () => $this->issue?->relationLoaded('volume')
                ? ['volume' => $this->issue->volume->number, 'number' => $this->issue->number, 'season' => $this->issue->season]
                : null),
            'doi' => $this->doi(),
            'doiUrl' => $this->doiUrl(),
            'views' => $this->views_count,
            // NULL — shown as "—" — until the article has a DOI. Nothing on this site can
            // count citations: they come from citation indexes (Crossref Cited-by, OpenAlex),
            // which can only track an article by its DOI. Printing "0" before then claims
            // that nobody has cited the paper, which is not something we know. When DOIs go
            // live, a scheduled sync must fill citations_count from one of those indexes.
            'citations' => $this->doi() !== null ? $this->citations_count : null,
            'photo' => $this->photo_key,
            'thumbnail' => $this->thumbnail(),
            'abstract' => $this->abstract,
            'keywords' => $this->keywords ?? [],
        ];
    }

    /** @return list<string> */
    /**
     * The card image: a preview of the article's own first page, or else the cover of the
     * issue it was printed in (when the caller loaded issue.coverMedia). Never a stock photo
     * — on a research paper that would misrepresent the work.
     *
     * @return array{url: string, kind: 'page'|'cover'}|null
     */
    protected function thumbnail(): ?array
    {
        if (filled($this->thumbnail_path)) {
            return ['url' => Storage::disk('public')->url($this->thumbnail_path), 'kind' => 'page'];
        }

        if ($this->relationLoaded('issue') && $this->issue?->relationLoaded('coverMedia') && $this->issue->coverMedia) {
            return ['url' => $this->issue->coverMedia->url, 'kind' => 'cover'];
        }

        return null;
    }

    protected function displayAuthors(): array
    {
        // A corporate author still needs to appear in the author position — an article
        // that renders with an empty byline reads as an error to a human and is
        // unindexable to a machine.
        if ($this->hasCorporateAuthor()) {
            return [(string) $this->corporate_author];
        }

        return $this->whenLoaded(
            'authors',
            fn () => $this->authors->map(fn ($a) => $a->fullName())->values()->all(),
            [],
        );
    }
}
