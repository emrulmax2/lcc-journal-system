<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Article;
use App\Models\ArticleAuthor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArticleAuthor>
 */
class ArticleAuthorFactory extends Factory
{
    protected $model = ArticleAuthor::class;

    /**
     * orcid is NULL, and no state here will ever fill it with a fake one. An ORCID names
     * a real, identifiable person: a fabricated one that escapes a test fixture into a
     * Crossref deposit attributes someone else's work to them, and it propagates to every
     * downstream index. Tests that need an ORCID must supply a known-safe value
     * explicitly.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'given_name' => fake()->firstName(),
            'family_name' => fake()->lastName(),
            'affiliation' => fake()->company(),
            'orcid' => null,
            'is_corresponding' => false,

            // Author order is meaningful. Tests that care must set this — usually with
            // ->sequence() or a ->count(n) plus an explicit ordinal.
            'sequence' => 1,
        ];
    }
}
