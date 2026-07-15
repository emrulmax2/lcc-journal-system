<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubmissionFileType;
use App\Enums\SubmissionStage;
use App\Enums\SubmissionStatus;
use Database\Factories\SubmissionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Submission extends Model
{
    /** @use HasFactory<SubmissionFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'stage' => SubmissionStage::class,
            'keywords' => 'array',
            'ethics_declared' => 'boolean',
            'conflicts_declared' => 'boolean',
            'data_available' => 'boolean',
            'declarations_at' => 'datetime',
            'submitted_at' => 'datetime',
            'draft_step' => 'integer',
        ];
    }

    // --- Relations ----------------------------------------------------------

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(JournalSection::class, 'journal_section_id');
    }

    /** The account that owns the submission — not necessarily the first-listed author. */
    public function correspondingAuthor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corresponding_author_id');
    }

    /** The Article this became on acceptance. NULL until then. */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /** Order is meaningful. Never sort these for display. */
    public function authors(): HasMany
    {
        return $this->hasMany(SubmissionAuthor::class)->orderBy('sequence');
    }

    public function files(): HasMany
    {
        return $this->hasMany(SubmissionFile::class);
    }

    /** The current manuscript — the highest version of it. Revisions append, never overwrite. */
    public function latestManuscript(): HasOne
    {
        return $this->hasOne(SubmissionFile::class)
            ->whereIn('type', [SubmissionFileType::Manuscript, SubmissionFileType::Revision])
            ->orderByDesc('version')
            ->orderByDesc('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SubmissionEvent::class)->orderBy('created_at');
    }

    public function reviewRounds(): HasMany
    {
        return $this->hasMany(ReviewRound::class)->orderBy('round_number');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(EditorialDecision::class)->orderBy('decided_at');
    }

    // --- The audit trail ----------------------------------------------------

    /**
     * THE ONLY WAY A SUBMISSION EVENT IS EVER WRITTEN.
     *
     * Every state transition in this system calls it — submitted, reviewer invited,
     * invitation accepted or declined, report submitted, decision recorded, converted to
     * an article. Editorial decisions get challenged years later, and "who assigned that
     * reviewer, and when" has to be answerable from the database. If you add a transition,
     * add the event with it; a transition that leaves no trace did not happen as far as an
     * integrity investigation is concerned.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function recordEvent(string $event, ?array $payload = null, ?User $user = null): SubmissionEvent
    {
        return $this->events()->create([
            'user_id' => $user?->id,
            'event' => $event,
            'payload' => $payload,
        ]);
    }

    // --- State --------------------------------------------------------------

    public function isDraft(): bool
    {
        return $this->status === SubmissionStatus::Draft;
    }

    /** The round currently taking reports. At most one; NULL between rounds. */
    public function openRound(): ?ReviewRound
    {
        return $this->reviewRounds()
            ->whereNull('closed_at')
            ->orderByDesc('round_number')
            ->first();
    }

    public function currentRound(): ?ReviewRound
    {
        return $this->reviewRounds()->orderByDesc('round_number')->first();
    }

    /**
     * Days from submission to the FIRST decision. NULL while either end is missing.
     *
     * The first decision is the one the metric means: a manuscript that goes round twice
     * has not taken 140 days to a first decision, it took 40 and was then revised.
     */
    public function daysToFirstDecision(): ?int
    {
        $first = $this->decisions()->orderBy('decided_at')->first();

        if ($first === null || $this->submitted_at === null) {
            return null;
        }

        return (int) $this->submitted_at->startOfDay()->diffInDays($first->decided_at->startOfDay());
    }

    // --- Scopes -------------------------------------------------------------

    /** Everything an editor may see. A DRAFT is not in it: nothing goes to an editor early. */
    public function scopeVisibleToEditors(Builder $query): Builder
    {
        return $query->where('status', '!=', SubmissionStatus::Draft);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            SubmissionStatus::Submitted,
            SubmissionStatus::UnderReview,
            SubmissionStatus::RevisionsRequested,
        ]);
    }
}
