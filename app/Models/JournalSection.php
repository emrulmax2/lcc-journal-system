<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\JournalSectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalSection extends Model
{
    /** @use HasFactory<JournalSectionFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'is_active' => 'boolean',

            // Front matter gets no DOI; editorials do. The Crossref XML builder skips
            // articles whose section has this false.
            'doi_eligible' => 'boolean',
        ];
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'journal_section_id');
    }
}
