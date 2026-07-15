<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Journal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * NOTE: crossref_username and crossref_password are absent by design and must stay
 * absent. The password is encrypted at rest, hidden on the model, and never serialised
 * — three independent layers, because a credential leaked into a JSON payload is
 * unrecoverable once it has been cached, logged or proxied.
 *
 * @mixin Journal
 */
class JournalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $metric = $this->whenLoaded('metric') ? $this->metric : null;

        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'abbreviation' => $this->abbreviation,
            'field' => $this->whenLoaded('field', fn () => $this->field?->name),
            'description' => $this->description,

            // A real, self-hosted cover. Falls back to photo_key (Unsplash) until one is
            // uploaded — the resolution order the whole site follows is
            // image -> photo -> a neutral placeholder, never a random substitute photo.
            'coverImage' => $this->whenLoaded('coverMedia', fn () => $this->coverMedia?->only(['url', 'alt', 'caption', 'credit'])),
            'photo' => $this->photo_key,
            'openAccess' => (bool) $this->open_access,
            'publicationModel' => $this->publication_model->value,

            // Externally sourced (JCR / Scopus). The UI must label these as such — they
            // are not ours to compute and presenting them as our own numbers would be a
            // misrepresentation.
            'impactFactor' => $metric?->impact_factor,
            'citeScore' => $metric?->cite_score,
            'metricsExternalAsOf' => $metric?->external_updated_at?->toDateString(),

            // Computed from our own data on a schedule.
            'acceptanceRate' => $metric?->acceptance_rate,
            'medianDaysToDecision' => $metric?->median_days_to_decision,
            'articles' => $metric?->article_count ?? 0,
            'editors' => $metric?->editor_count ?? 0,
            'metricsComputedAt' => $metric?->computed_at?->toDateString(),
        ];
    }
}
