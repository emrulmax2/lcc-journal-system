<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Journal;
use App\Models\JournalSection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JournalSection>
 */
class JournalSectionFactory extends Factory
{
    protected $model = JournalSection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'journal_id' => Journal::factory(),

            // (journal_id, name) is unique.
            'name' => Str::title(fake()->unique()->words(2, true)),
            'sequence' => 0,
            'is_active' => true,
            'doi_eligible' => true,
        ];
    }

    /** Front matter and similar: real content, but nothing Crossref should be given a DOI for. */
    public function notDoiEligible(): static
    {
        return $this->state(fn (array $attributes): array => [
            'doi_eligible' => false,
        ]);
    }
}
