<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubmissionFileType;
use App\Models\Submission;
use App\Models\SubmissionFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SubmissionFile> */
class SubmissionFileFactory extends Factory
{
    protected $model = SubmissionFile::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory(),
            'version' => 1,
            'type' => SubmissionFileType::Manuscript,
            'path' => 'submissions/'.fake()->uuid().'.pdf',
            'original_name' => 'manuscript.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(200_000, 4_000_000),
            'uploaded_by' => null,
        ];
    }
}
