<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PublicationModel;
use Database\Factories\JournalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Journal extends Model
{
    /** @use HasFactory<JournalFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'publication_model' => PublicationModel::class,
            'open_access' => 'boolean',
            'is_active' => 'boolean',
            'crossref_deposit_references' => 'boolean',
            'doi_sequence_padding' => 'integer',

            // Encrypted at rest. Never appears in a response (see JournalResource),
            // never logged, never serialised into a queue payload.
            'crossref_password' => 'encrypted',
        ];
    }

    /**
     * Hidden as a second line of defence. The API layer already strips it, but a
     * ->toArray() somewhere in a log line or a debug dump must not leak it either.
     */
    protected $hidden = ['crossref_password'];

    /**
     * The real, self-hosted cover image.
     *
     * Falls back to photo_key (an Unsplash stock photo) until one is uploaded. Shipping a
     * live LCC journal illustrated with stock photography of someone else's laboratory is
     * not acceptable, so the intent is that this stops being NULL before launch.
     */
    public function coverMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    public function metric(): HasOne
    {
        return $this->hasOne(JournalMetric::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(JournalSection::class)->orderBy('sequence');
    }

    public function volumes(): HasMany
    {
        return $this->hasMany(Volume::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    /** Includes DRAFTS, which only their own author may see. Scope before you serialise. */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(DoiDeposit::class);
    }

    /**
     * Users carrying a role scoped to THIS journal. See the Spatie teams config.
     *
     * distinct(): model_has_roles is keyed per role, not per team, so a user who is both
     * section-editor and reviewer on this journal has two rows and would otherwise be
     * listed twice. User::journals() is deduped the same way — keep the two in step.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'model_has_roles', 'journal_id', 'model_id')
            ->wherePivot('model_type', User::class)
            ->distinct();
    }

    /**
     * A journal cannot deposit DOIs until Crossref has issued a prefix. This gate is
     * why doi_prefix is NULL rather than a "10.xxxx" placeholder — a placeholder would
     * happily deposit and mint garbage identifiers.
     */
    public function canMintDois(): bool
    {
        return filled($this->doi_prefix);
    }

    public function usesIssues(): bool
    {
        return $this->publication_model->usesIssues();
    }

    /*
     * NOTE: there is deliberately NO getRouteKeyName() here.
     *
     * The two halves of the app bind journals differently, and that is on purpose:
     *
     *   PUBLIC  /journals/{journal:slug}  — a human-readable, shareable, permanent URL.
     *                                       Declared with an explicit :slug in web.php.
     *   ADMIN   /admin/journals/{journal} — by id. The admin edits across every journal
     *                                       including inactive ones, and an id is stable
     *                                       even if a slug is later corrected.
     *
     * Setting the model-wide key to `slug` looks tidier and immediately breaks every
     * admin route, because they are generated from ids. Keep the difference explicit at
     * the route, where you can see it.
     */
}
