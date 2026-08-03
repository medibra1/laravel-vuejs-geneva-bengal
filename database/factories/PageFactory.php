<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'menu_group' => null,
            'order' => fake()->numberBetween(0, 20),
            'title' => ['fr' => fake()->sentence(3), 'en' => fake()->sentence(3)],
            'body' => ['fr' => fake()->paragraphs(3, true), 'en' => fake()->paragraphs(3, true)],
            'meta_title' => ['fr' => fake()->sentence(4), 'en' => fake()->sentence(4)],
            'meta_description' => ['fr' => fake()->sentence(15), 'en' => fake()->sentence(15)],
            'is_published' => true,
        ];
    }
}
