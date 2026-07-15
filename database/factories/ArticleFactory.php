<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Issue;
use App\Models\Journal;
use App\Models\Volume;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    protected $model = Article::class;

    /**
     * A draft article in an issue of its own journal, with no DOI suffix and no authors.
     *
     * doi_suffix is NULL because only DoiSuffixGenerator may fill it in. A factory that
     * invented one would be a second construction site for identifiers, which is exactly
     * the failure DoiSuffixGenerator's docblock exists to prevent. Tests that need a
     * suffix should generate it, or set it explicitly and mean it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstPage = fake()->numberBetween(1, 400);

        return [
            // journal_id is declared FIRST on purpose: Laravel expands definition
            // attributes in order, so issue_id's closure below sees a resolved id here
            // (whether it came from this factory or from a ->for($journal) state) and can
            // hang the issue off the same journal. An article whose issue belongs to a
            // different journal is not a row any real code path can produce.
            'journal_id' => Journal::factory(),

            'issue_id' => fn (array $attributes) => Issue::factory()->for(
                Volume::factory()->state(['journal_id' => $attributes['journal_id']])
            ),

            'sequence' => fake()->unique()->numberBetween(1, 999),
            'slug' => fake()->unique()->slug(),
            'doi_suffix' => null,

            'title' => fake()->sentence(8),
            'abstract' => fake()->paragraph(),
            'keywords' => fake()->words(4),

            // last_page >= first_page. A range that runs backwards is nonsense in a
            // citation and prints as "42–17".
            'first_page' => $firstPage,
            'last_page' => $firstPage + fake()->numberBetween(0, 20),

            'status' => ArticleStatus::Draft,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ArticleStatus::Published,
            'published_at' => now(),
        ]);
    }

    /**
     * An article by a research centre rather than named people — JCD&MS Vol 10 No 2
     * Article 001 is exactly this. It deliberately creates NO article_authors rows: zero
     * personal authors is the valid, correct shape here, and it is the case that breaks
     * naive citation code and naive Crossref XML.
     */
    public function corporate(string $name = 'Members of the Centre for Learning Innovation and Research (CLIR), London Churchill College'): static
    {
        return $this->state(fn (array $attributes): array => [
            'corporate_author' => $name,
        ]);
    }

    /** Continuous publication: no issue to belong to, and therefore no page numbers. */
    public function continuous(): static
    {
        return $this->state(fn (array $attributes): array => [
            'issue_id' => null,
            'first_page' => null,
            'last_page' => null,
        ]);
    }
}
