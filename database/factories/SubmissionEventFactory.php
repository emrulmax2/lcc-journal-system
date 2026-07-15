<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Submission;
use App\Models\SubmissionEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SubmissionEvent> */
class SubmissionEventFactory extends Factory
{
    protected $model = SubmissionEvent::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory(),
            'user_id' => null,
            'event' => 'submission.submitted',
            'payload' => null,
            'created_at' => now(),
        ];
    }
}
