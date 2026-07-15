<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ArticleFileType;
use App\Enums\ArticleStatus;
use App\Observers\ArticleObserver;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[ObservedBy(ArticleObserver::class)]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * The attributes publication makes permanent. ArticleObserver refuses to let any of
     * these change once status is published. Kept here, public, so the observer, the
     * policy and the tests all read the same list rather than three drifting copies.
     *
     * @var array<int, string>
     */
    public const FROZEN_ON_PUBLISH = ['slug', 'sequence', 'doi_suffix'];

    protected function casts(): array
    {
        return [
            'status' => ArticleStatus::class,
            'keywords' => 'array',
            'published_at' => 'datetime',
            'views_count' => 'integer',
            'citations_count' => 'integer',
        ];
    }

    // --- Relations ----------------------------------------------------------

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * The article's own figure — a real asset, uploaded with the manuscript.
     *
     * The article page currently captions a stock photo as "Figure 1. Representative
     * imagery from the study site." It is not from the study site; it is a stranger's
     * laboratory. On a published paper that is a fabrication, not a placeholder. When this
     * is NULL the page must render NO figure at all rather than fall back to stock.
     */
    public function heroMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'hero_media_id');
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(JournalSection::class, 'journal_section_id');
    }

    /** Order is meaningful. Never sort these for display. */
    public function authors(): HasMany
    {
        return $this->hasMany(ArticleAuthor::class)->orderBy('sequence');
    }

    public function references(): HasMany
    {
        return $this->hasMany(ArticleReference::class)->orderBy('ordinal');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ArticleFile::class);
    }

    public function pdf(): HasOne
    {
        return $this->hasOne(ArticleFile::class)->where('type', ArticleFileType::Pdf);
    }

    public function dailyMetrics(): HasMany
    {
        return $this->hasMany(ArticleMetricDaily::class);
    }

    public function depositItems(): HasMany
    {
        return $this->hasMany(DoiDepositItem::class);
    }

    // --- Identity -----------------------------------------------------------

    /**
     * The DOI. NULL until Crossref has issued the journal a prefix AND the article has
     * a suffix — a half-formed DOI is worse than none, because it looks usable.
     *
     * Everything downstream reads this: the landing-page meta tags, all three citation
     * formats, the Crossref XML, the sitemap. Changing journals.doi_prefix on one row
     * moves every DOI that journal owns, with no other code change. That is the test.
     */
    public function doi(): ?string
    {
        $prefix = $this->journal?->doi_prefix;

        if (blank($prefix) || blank($this->doi_suffix)) {
            return null;
        }

        return "{$prefix}/{$this->doi_suffix}";
    }

    /** The resolvable form. A DOI printed as bare text is not a link and cannot be followed. */
    public function doiUrl(): ?string
    {
        $doi = $this->doi();

        return $doi === null ? null : "https://doi.org/{$doi}";
    }

    /** The canonical landing page. citation_abstract_html_url must equal this exactly. */
    public function landingUrl(): string
    {
        return route('articles.show', $this->slug);
    }

    /** The stable PDF route. citation_pdf_url must equal this exactly. */
    public function pdfUrl(): string
    {
        return route('articles.pdf', $this->slug);
    }

    /**
     * Advertising a citation_pdf_url that 404s is worse than advertising none: Scholar
     * fetches it, fails, and quietly downgrades the whole journal.
     */
    public function hasPdf(): bool
    {
        return $this->relationLoaded('pdf')
            ? $this->pdf !== null
            : $this->files()->where('type', ArticleFileType::Pdf)->exists();
    }

    public function pageRange(): ?string
    {
        if ($this->first_page === null) {
            return null;
        }

        return $this->last_page === null || $this->last_page === $this->first_page
            ? (string) $this->first_page
            : "{$this->first_page}–{$this->last_page}";   // en-dash: this is a page RANGE
    }

    /**
     * An editorial by a research centre has no personal authors. Crossref needs an
     * <organization> contributor for these, not a <person_name>, and the citation
     * formats need the corporate name in the author position.
     */
    public function hasCorporateAuthor(): bool
    {
        return filled($this->corporate_author);
    }

    public function isPublished(): bool
    {
        return $this->status === ArticleStatus::Published;
    }

    public function isFrozen(): bool
    {
        return $this->status->isFrozen();
    }

    // --- Scopes -------------------------------------------------------------

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ArticleStatus::Published);
    }

    /**
     * FULLTEXT search over title + abstract, plus a LIKE fallback across keywords and
     * author names so the result set matches what the frontend used to compute in the
     * browser. IN BOOLEAN MODE so that short queries and partial words still match —
     * natural-language mode silently drops terms appearing in >50% of rows, which on a
     * seven-article journal means almost everything.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->whereFullText(['title', 'abstract'], $term, ['mode' => 'boolean'])
                ->orWhere('title', 'like', '%'.$term.'%')
                ->orWhere('abstract', 'like', '%'.$term.'%')
                ->orWhere('keywords', 'like', '%'.$term.'%')
                ->orWhereHas('authors', function (Builder $a) use ($term) {
                    $a->where('given_name', 'like', '%'.$term.'%')
                        ->orWhere('family_name', 'like', '%'.$term.'%');
                });
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
