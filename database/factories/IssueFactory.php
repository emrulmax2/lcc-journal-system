<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Volume;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Issue>
 */
class IssueFactory extends Factory
{
    protected $model = Issue::class;

    /**
     * Draft, with no publication_date — the state an issue is in for most of its life.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'volume_id' => Volume::factory(),

            // (volume_id, number) is unique — see VolumeFactory for why these are unique.
            'number' => fake()->unique()->numberBetween(1, 5000),
            'season' => fake()->randomElement(['Spring', 'Summer', 'Autumn', 'Winter'])
                .' '.fake()->numberBetween(2000, 2026),

            'status' => IssueStatus::Draft,
            'publication_date' => null,
        ];
    }

    /** Published issues carry a date; a published issue with no date is not a thing. */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => IssueStatus::Published,
            'publication_date' => now()->toDateString(),
        ]);
    }
}
