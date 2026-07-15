<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DecisionType;
use App\Models\EditorialDecision;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EditorialDecision> */
class EditorialDecisionFactory extends Factory
{
    protected $model = EditorialDecision::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory()->underReview(),
            'review_round_id' => null,
            'editor_id' => User::factory(),
            'decision' => DecisionType::MinorRevision,
            'body' => fake()->paragraph(),
            'decided_at' => now(),
        ];
    }
}
