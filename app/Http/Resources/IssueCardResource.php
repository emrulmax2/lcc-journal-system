<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Issue;
use App\Models\Journal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One card per published ISSUE — "Journal of Contemporary Development & Management Studies,
 * Volume 5 · Number 1 · Summer 2017".
 *
 * An issue-based journal is read issue by issue, the way it was printed. A single journal
 * card holding all 73 articles from eight issues hid that structure, so the journal lists
 * (/journals, the homepage, the journal page) show its issues instead.
 *
 * The metrics on the card are honest about their scope: `articles` is THIS issue's count;
 * the impact factor and the editors belong to the journal and are labelled by the card as
 * such. An impact factor the journal does not have renders "—", never a number.
 *
 * @mixin Issue
 */
class IssueCardResource extends JsonResource
{
    /**
     * Published issues of active journals, newest first, with everything the card needs
     * eager-loaded — one query per relation, not one per card.
     *
     * @return Builder<Issue>
     */
    public static function query(?Journal $journal = null): Builder
    {
        return Issue::query()
            ->published()
            ->whereHas('volume.journal', fn (Builder $q) => $q
                ->where('is_active', true)
                ->when($journal, fn (Builder $q) => $q->whereKey($journal->id)))
            ->with(['volume.journal.metric', 'volume.journal.coverMedia', 'coverMedia'])
            ->withCount(['articles as published_articles_count' => fn (Builder $q) => $q->published()])
            ->orderByDesc('publication_date')
            ->orderByDesc('number');
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $journal = $this->volume->journal;

        return [
            'key' => "{$journal->slug}-v{$this->volume->number}n{$this->number}",
            'url' => $this->url(),
            'volume' => $this->volume->number,
            'number' => $this->number,
            'season' => $this->season,
            'year' => $this->publication_date?->year ?? $this->volume->year,
            'publicationDate' => $this->publication_date?->toDateString(),
            'articles' => (int) ($this->published_articles_count ?? 0),

            // The printed cover. When there is none the card falls back to the journal's
            // own image — never to an unrelated stock photograph.
            'cover' => $this->coverMedia?->only(['url', 'alt']),

            'journal' => [
                'slug' => $journal->slug,
                'title' => $journal->title,
                'abbreviation' => $journal->abbreviation,
                'openAccess' => (bool) $journal->open_access,
                'image' => $journal->coverMedia?->only(['url', 'alt', 'caption', 'credit']),
                'photo' => $journal->photo_key,
                'impactFactor' => $journal->metric?->impact_factor,
                'editors' => $journal->metric?->editor_count ?? 0,
            ],
        ];
    }
}
