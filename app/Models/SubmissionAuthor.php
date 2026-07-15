<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SubmissionAuthorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionAuthor extends Model
{
    /** @use HasFactory<SubmissionAuthorFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_corresponding' => 'boolean',
            'sequence' => 'integer',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /**
     * Split a submitted name into Crossref's given/family pair ON THE LAST SPACE.
     *
     * "Maria del Carmen Ramírez" → given "Maria del Carmen", family "Ramírez". A single
     * word ("Aristotle", or a mononym) has no family name to split off, so it becomes the
     * family name and the given name is empty — that is what Crossref's surname-only
     * contributor looks like, and it beats guessing.
     *
     * @return array{0: string, 1: string} [given, family]
     */
    public function splitName(): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $this->name) ?? '');

        $cut = mb_strrpos($name, ' ');

        if ($cut === false) {
            return ['', $name];
        }

        return [mb_substr($name, 0, $cut), mb_substr($name, $cut + 1)];
    }
}
