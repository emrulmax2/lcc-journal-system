<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IssueStatus;
use Database\Factories\IssueFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issue extends Model
{
    /** @use HasFactory<IssueFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => IssueStatus::class,

            // NULL until published. A date cast, not datetime: an issue is dated to a
            // day, and a time-of-day here would be invented precision in the deposit.
            'publication_date' => 'date',
        ];
    }

    // --- Relations ----------------------------------------------------------

    public function volume(): BelongsTo
    {
        return $this->belongsTo(Volume::class);
    }

    /** The printed cover, from the media library. NULL renders the journal's own image. */
    public function coverMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    /** Order is meaningful — it is the running order of the issue, not a sort. */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class)->orderBy('sequence');
    }

    /**
     * The owning journal, reached through the volume. An accessor rather than a
     * relation: the FK chain runs upward (issues -> volumes -> journals), which no
     * Eloquent has-through relation models. It cannot be eager-loaded on its own —
     * load `volume.journal` and this reads from that.
     */
    public function getJournalAttribute(): ?Journal
    {
        return $this->volume?->journal;
    }

    // --- State --------------------------------------------------------------

    public function isPublished(): bool
    {
        return $this->status === IssueStatus::Published;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', IssueStatus::Published);
    }

    // --- Presentation -------------------------------------------------------

    /** "Volume 5, Number 1" — the label printed on the cover and used in every heading. */
    public function label(): string
    {
        return "Volume {$this->volume->number}, Number {$this->number}";
    }

    /** "Volume 5, Number 1 · Summer 2017" */
    public function labelWithSeason(): string
    {
        return filled($this->season) ? "{$this->label()} · {$this->season}" : $this->label();
    }

    /**
     * Issues an author may aim a submission at: the journal's UNPUBLISHED issues, which the
     * editors open in the admin when they plan the next one. Oldest first — the soonest
     * issue is the likeliest home.
     */
    public function scopeOpenForSubmissions(Builder $query, Journal $journal): Builder
    {
        return $query
            ->where('status', IssueStatus::Draft)
            ->whereHas('volume', fn (Builder $v) => $v->where('journal_id', $journal->id))
            ->join('volumes', 'volumes.id', '=', 'issues.volume_id')
            ->orderBy('volumes.number')
            ->orderBy('issues.number')
            ->select('issues.*');
    }

    /**
     * The public issue page. Built from the volume and issue NUMBERS rather than the row
     * id, so the address reads the way the issue is cited and survives a database rebuild.
     * Load `volume.journal` before calling this.
     */
    public function url(): string
    {
        return route('journals.issues.show', [
            'journal' => $this->volume->journal->slug,
            'volume' => $this->volume->number,
            'number' => $this->number,
        ]);
    }

    public function fullPdfUrl(): ?string
    {
        if (blank($this->full_pdf_path)) {
            return null;
        }

        return route('journals.issues.pdf', [
            'journal' => $this->volume->journal->slug,
            'volume' => $this->volume->number,
            'number' => $this->number,
        ]);
    }
}
