<?php

namespace Database\Factories;

use App\Models\FaqItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
        // fake()->unique() (not just Str::slug on a plain sentence): two
        // Faker sentences can coincidentally slug down to the same string,
        // which would violate the new unique `slug` column on a second
        // factory call in the same test.
        $question = fake()->unique()->sentence().'?';

        return [
            'question' => ['fr' => $question, 'en' => fake()->sentence().'?'],
            'answer' => ['fr' => fake()->paragraph(), 'en' => fake()->paragraph()],
            'order' => fake()->numberBetween(0, 20),
            'slug' => Str::slug($question),
        ];
    }
}
