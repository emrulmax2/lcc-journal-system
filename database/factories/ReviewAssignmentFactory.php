<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReviewerStatus;
use App\Models\ReviewAssignment;
use App\Models\ReviewRound;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReviewAssignment> */
class ReviewAssignmentFactory extends Factory
{
    protected $model = ReviewAssignment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'review_round_id' => ReviewRound::factory(),
            'reviewer_id' => User::factory(),
            'status' => ReviewerStatus::Invited,
            'invited_at' => now()->subDays(7),
            'due_at' => now()->addDays(14),
            'responded_at' => null,
            'completed_at' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReviewerStatus::Accepted,
            'responded_at' => now()->subDays(6),
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReviewerStatus::Declined,
            'responded_at' => now()->subDays(6),
        ]);
    }

    public function reported(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReviewerStatus::ReportSubmitted,
            'responded_at' => now()->subDays(6),
            'completed_at' => now()->subDay(),
        ]);
    }

    /** Past its due date and still owed — what the overdue banner counts. */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'due_at' => now()->subDays(3),
        ]);
    }
}
