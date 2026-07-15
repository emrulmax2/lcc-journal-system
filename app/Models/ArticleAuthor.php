<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ArticleAuthorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleAuthor extends Model
{
    /** @use HasFactory<ArticleAuthorFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_corresponding' => 'boolean',
            'sequence' => 'integer',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function fullName(): string
    {
        return trim("{$this->given_name} {$this->family_name}");
    }

    public function hasOrcid(): bool
    {
        return filled($this->orcid);
    }

    /**
     * The resolvable ORCID. NULL where the author has none — never synthesise one, a
     * wrong ORCID attributes this work to a real, identifiable other person.
     */
    public function orcidUrl(): ?string
    {
        return $this->hasOrcid() ? "https://orcid.org/{$this->orcid}" : null;
    }
}
