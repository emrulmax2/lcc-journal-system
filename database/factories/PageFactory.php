<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'slug' => Str::slug($title),
            'title' => $title,
            'summary' => fake()->sentence(),
            'body' => "## A heading\n\nSome **markdown**.",
            'status' => 'draft',
            'published_at' => null,
            'is_system' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
    }

    /** The footer links to it structurally. Page::booted() refuses to delete one. */
    public function system(): static
    {
        return $this->state(fn (): array => ['is_system' => true]);
    }
}
