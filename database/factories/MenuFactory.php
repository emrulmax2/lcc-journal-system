<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'location' => 'footer',
            'sequence' => 0,
        ];
    }
}
