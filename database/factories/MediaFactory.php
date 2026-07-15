<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'disk' => 'public',
            'path' => 'media/2026/07/'.fake()->uuid().'.jpg',
            'original_name' => fake()->slug(2).'.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => fake()->numberBetween(20_000, 2_000_000),
            'width' => 1600,
            'height' => 900,
            'alt' => fake()->sentence(),
            'caption' => null,
            'credit' => null,
        ];
    }

    /** An image nobody has described. NOT the same as a decorative one. */
    public function undescribed(): static
    {
        return $this->state(fn (): array => ['alt' => null]);
    }

    /** '' — a deliberate statement that a screen reader should skip it. */
    public function decorative(): static
    {
        return $this->state(fn (): array => ['alt' => '']);
    }
}
