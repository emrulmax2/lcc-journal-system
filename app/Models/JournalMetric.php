<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\JournalMetricFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalMetric extends Model
{
    /** @use HasFactory<JournalMetricFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            // Externally sourced (JCR / Scopus), entered by hand. external_updated_at
            // says when — the UI must show it, so a stale figure cannot pass as current.
            'impact_factor' => 'float',
            'cite_score' => 'float',
            'external_updated_at' => 'datetime',

            // Computed from our own data. computed_at is set by the job that derives them.
            'acceptance_rate' => 'integer',
            'median_days_to_decision' => 'integer',
            'article_count' => 'integer',
            'editor_count' => 'integer',
            'computed_at' => 'datetime',
        ];
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
}
