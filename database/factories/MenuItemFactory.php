<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * A route-backed item by default.
     *
     * MenuItem::saving() throws unless EXACTLY ONE destination is set, so there is no such
     * thing as a "blank" menu item to fabricate — the default has to point somewhere real.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'menu_id' => Menu::factory(),
            'label' => fake()->words(2, true),
            'description' => null,
            'page_id' => null,
            'route_name' => 'journals.index',
            'external_url' => null,
            'parent_id' => null,
            'opens_in_new_tab' => false,
            'is_active' => true,
            'sequence' => 0,
        ];
    }
}
