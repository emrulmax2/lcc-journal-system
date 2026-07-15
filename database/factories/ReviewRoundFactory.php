<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ReviewRound;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReviewRound> */
class ReviewRoundFactory extends Factory
{
    protected $model = ReviewRound::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory()->underReview(),
            'round_number' => 1,
            'opened_at' => now()->subDays(7),
            'closed_at' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'closed_at' => now(),
        ]);
    }
}
