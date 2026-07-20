<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'prefix' => Str::upper(fake()->unique()->randomLetter()),
            'color' => fake()->hexColor(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
