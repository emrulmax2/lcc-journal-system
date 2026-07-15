<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Journal;
use App\Models\Volume;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Volume>
 */
class VolumeFactory extends Factory
{
    protected $model = Volume::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'journal_id' => Journal::factory(),

            // (journal_id, number) is unique. Distinct numbers keep a test that makes
            // several volumes on one journal — via ->for($journal) — from colliding.
            'number' => fake()->unique()->numberBetween(1, 5000),
            'year' => fake()->numberBetween(2000, 2026),
        ];
    }
}
