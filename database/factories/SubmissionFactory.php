<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubmissionStage;
use App\Enums\SubmissionStatus;
use App\Models\Journal;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A DRAFT by default — the state every submission starts in, and the one with no
 * reference. `reference` is NULL here on purpose: only SubmitManuscriptAction may mint
 * one, so that the per-journal-per-year sequence has exactly one construction site.
 *
 * @extends Factory<Submission>
 */
class SubmissionFactory extends Factory
{
    protected $model = Submission::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'reference' => null,
            'journal_id' => Journal::factory(),
            'journal_section_id' => null,
            'corresponding_author_id' => User::factory(),

            'title' => fake()->sentence(8),
            'abstract' => fake()->paragraph(4),
            'keywords' => fake()->words(4),

            'status' => SubmissionStatus::Draft,
            'stage' => SubmissionStage::Submitted,

            'funding' => null,
            'ethics_declared' => false,
            'conflicts_declared' => false,
            'data_available' => false,
            'declarations_at' => null,

            'submitted_at' => null,
            'article_id' => null,

            'draft_step' => 0,
            'draft_file_name' => null,
        ];
    }

    /** Sent to an editor. Tests that need a REAL reference should go through the action. */
    public function submitted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'reference' => strtoupper(fake()->unique()->bothify('TEST-2026-####')),
            'status' => SubmissionStatus::Submitted,
            'stage' => SubmissionStage::EditorCheck,
            'ethics_declared' => true,
            'conflicts_declared' => true,
            'declarations_at' => now(),
            'submitted_at' => now()->subDays(10),
            'draft_step' => null,
            'draft_file_name' => null,
        ]);
    }

    public function underReview(): static
    {
        return $this->submitted()->state(fn (array $attributes): array => [
            'status' => SubmissionStatus::UnderReview,
            'stage' => SubmissionStage::PeerReview,
        ]);
    }
}
