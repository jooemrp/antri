<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = $this->faker->unique()->numberBetween(1, 999);

        return [
            'service_id' => Service::factory(),
            'loket_id' => null,
            'number' => $number,
            'code' => sprintf('A-%03d', $number),
            'status' => Ticket::STATUS_WAITING,
            'issued_date' => today(),
            'called_at' => null,
            'served_at' => null,
            'finished_at' => null,
        ];
    }
}
