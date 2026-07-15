<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Recommendation;
use App\Models\Review;
use App\Models\ReviewAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Review> */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'review_assignment_id' => ReviewAssignment::factory()->reported(),
            'recommendation' => Recommendation::MinorRevision,
            'comments_to_author' => fake()->paragraph(),

            // Deliberately populated by default: the leak tests need a value that WOULD
            // show up in a response if the anonymity layer ever failed open.
            'comments_to_editor' => fake()->paragraph(),
            'submitted_at' => now()->subDay(),
        ];
    }
}
