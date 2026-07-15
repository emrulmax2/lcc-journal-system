<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PublicationModel;
use App\Models\Field;
use App\Models\Journal;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Journal>
 */
class JournalFactory extends Factory
{
    protected $model = Journal::class;

    /**
     * Issue-based by default, because JCD&MS is and most tests are about it.
     *
     * doi_prefix is NULL by default, and that is not laziness: it is the real state of a
     * journal Crossref has not issued a prefix to, so Journal::canMintDois() is false and
     * Article::doi() returns NULL. Tests that need a resolvable DOI must say so, via
     * ->withDoiPrefix(). That way nothing can accidentally mint an identifier under a
     * prefix that does not belong to us.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(fake()->unique()->words(3, true));

        return [
            'slug' => Str::slug($title),
            'title' => $title,
            'abbreviation' => Str::upper(Str::substr(Str::remove(' ', $title), 0, 5)),
            'field_id' => Field::factory(),
            'description' => fake()->sentence(),

            'publication_model' => PublicationModel::IssueBased,
            'open_access' => true,

            'doi_prefix' => null,
            'doi_suffix_pattern' => '{journal}.v{volume}i{issue}.{seq}',
            'doi_sequence_padding' => 3,

            'is_active' => true,
        ];
    }

    /** No volumes, no issues, no page numbers — articles carry issue_id = NULL. */
    public function continuous(): static
    {
        return $this->state(fn (array $attributes): array => [
            'publication_model' => PublicationModel::Continuous,

            // {volume}/{issue} cannot resolve without an issue; DoiSuffixGenerator would
            // (correctly) refuse the issue-based pattern on this journal.
            'doi_suffix_pattern' => '{journal}.{year}.{seq}',
            'doi_sequence_padding' => 5,
        ]);
    }

    /** A journal Crossref has issued a prefix to, so its articles can hold a real DOI. */
    public function withDoiPrefix(string $prefix = '10.99999'): static
    {
        return $this->state(fn (array $attributes): array => [
            'doi_prefix' => $prefix,
        ]);
    }
}
