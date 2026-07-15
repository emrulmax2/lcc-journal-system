<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Field;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Field>
 */
class FieldFactory extends Factory
{
    protected $model = Field::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // fields.slug is unique, so the name it derives from has to be too.
        $name = Str::title(fake()->unique()->word());

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'sequence' => 0,
        ];
    }
}
