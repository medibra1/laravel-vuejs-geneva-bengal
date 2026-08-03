<?php

namespace Database\Factories;

use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'author_name' => fake()->name(),
            'quote' => ['fr' => fake()->paragraph(), 'en' => fake()->paragraph()],
            'rating' => fake()->numberBetween(3, 5),
            'is_published' => true,
            'order' => fake()->numberBetween(0, 20),
        ];
    }
}
