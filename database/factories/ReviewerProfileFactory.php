<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ReviewerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * orcid stays NULL — see ArticleAuthorFactory for why no factory here will ever invent one.
 *
 * @extends Factory<ReviewerProfile>
 */
class ReviewerProfileFactory extends Factory
{
    protected $model = ReviewerProfile::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'affiliation' => fake()->company(),
            'orcid' => null,
            'expertise' => fake()->words(3),
            'available' => true,
            'max_concurrent_reviews' => 3,
        ];
    }
}
