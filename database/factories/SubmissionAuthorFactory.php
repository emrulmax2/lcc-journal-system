<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Submission;
use App\Models\SubmissionAuthor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * orcid stays NULL, and no state here will ever fake one — an ORCID names a real,
 * identifiable person, and a fabricated one that escapes into a Crossref deposit
 * attributes someone else's work to them.
 *
 * @extends Factory<SubmissionAuthor>
 */
class SubmissionAuthorFactory extends Factory
{
    protected $model = SubmissionAuthor::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'affiliation' => fake()->company(),
            'orcid' => null,
            'is_corresponding' => false,
            'sequence' => 1,
        ];
    }

    public function corresponding(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_corresponding' => true,
            'sequence' => 1,
        ]);
    }
}
