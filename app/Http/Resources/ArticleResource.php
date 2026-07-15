<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'doi' => $this->doi(),
            'doiUrl' => $this->doiUrl(),
            'views' => $this->views_count,
            'citations' => $this->citations_count,
            'photo' => $this->photo_key,
            'abstract' => $this->abstract,
            'keywords' => $this->keywords ?? [],
        ];
    }

    /** @return list<string> */
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
