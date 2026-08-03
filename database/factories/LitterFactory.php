<?php

namespace Database\Factories;

use App\Models\Litter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Litter>
 */
class LitterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sire_cat_id' => null,
            'dam_cat_id' => null,
            'expected_date' => fake()->dateTimeBetween('now', '+4 months'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
