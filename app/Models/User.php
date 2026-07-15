<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'given_name',
        'family_name',
        'affiliation',
        'orcid',
        'avatar_path',
        'is_active',
        'is_site_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_site_admin' => 'boolean',
        ];
    }

    // --- Relations ----------------------------------------------------------

    public function reviewerProfile(): HasOne
    {
        return $this->hasOne(ReviewerProfile::class);
    }

    /** Manuscripts this user submitted — including drafts, which only they can see. */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'corresponding_author_id');
    }

    /** Invitations to review. NEVER load this onto anything an author can see. */
    public function reviewAssignments(): HasMany
    {
        return $this->hasMany(ReviewAssignment::class, 'reviewer_id');
    }

    /**
     * Journals this user carries a scoped role on. The pivot is Spatie's model_has_roles
     * with teams on, where the team key IS journal_id — see config/permission.php. The
     * model_type filter is not optional: without it, any other morphable model sharing
     * an id would be matched.
     */
    public function journals(): BelongsToMany
    {
        return $this->belongsToMany(Journal::class, 'model_has_roles', 'model_id', 'journal_id')
            ->wherePivot('model_type', self::class)
            ->distinct();
    }

    // --- Identity -----------------------------------------------------------

    /**
     * Crossref needs given and family names separately, so those are the real fields.
     * `name` is the login display name and is the fallback for accounts created before
     * the split, or by a flow that never asked for it.
     */
    public function fullName(): string
    {
        if (filled($this->given_name) && filled($this->family_name)) {
            return trim("{$this->given_name} {$this->family_name}");
        }

        return (string) $this->name;
    }

    public function hasOrcid(): bool
    {
        return filled($this->orcid);
    }

    public function orcidUrl(): ?string
    {
        return $this->hasOrcid() ? "https://orcid.org/{$this->orcid}" : null;
    }
}
