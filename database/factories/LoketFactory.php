<?php

namespace Database\Factories;

use App\Models\Loket;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loket>
 */
class LoketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'name' => 'Loket '.fake()->numberBetween(1, 10),
            'officer_name' => fake()->name(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
