<?php

namespace Database\Factories;

use App\Models\FaqItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FaqItem>
 */
class FaqItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question' => ['fr' => fake()->sentence().'?', 'en' => fake()->sentence().'?'],
            'answer' => ['fr' => fake()->paragraph(), 'en' => fake()->paragraph()],
            'order' => fake()->numberBetween(0, 20),
        ];
    }
}
