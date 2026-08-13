<?php

namespace Database\Factories;

use App\Models\Gallery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gallery>
 */
class GalleryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'caption' => fake()->optional()->sentence(),
            // unique(): backs the (type, position) unique DB index — two
            // factory calls landing on the same position for the same type
            // (e.g. count(10)->create(['type' => 'gallery'])) would
            // otherwise collide often enough in practice to be a real risk,
            // not just a theoretical one.
            'position' => fake()->unique()->numberBetween(0, 1000),
        ];
    }
}
