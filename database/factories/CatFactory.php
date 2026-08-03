<?php

namespace Database\Factories;

use App\Enums\CatSex;
use App\Enums\CatType;
use App\Models\Cat;
use App\Models\Color;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cat>
 */
class CatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'type' => fake()->randomElement(CatType::cases()),
            'sex' => fake()->randomElement(CatSex::cases()),
            'color_id' => Color::factory(),
            'description' => [
                'fr' => fake()->paragraph(),
                'en' => fake()->paragraph(),
            ],
            'price' => fake()->numberBetween(150000, 350000),
            'birth_date' => fake()->dateTimeBetween('-1 year', '-2 months'),
            'eye_color' => fake()->randomElement(['Vert', 'Or', 'Noisette']),
            'available_at' => fake()->dateTimeBetween('now', '+2 months'),
            'diet' => fake()->randomElement(['Croquettes premium', 'Alimentation mixte', 'Ration ménagère']),
            'litter_trained' => fake()->boolean(80),
            'neutered' => fake()->boolean(30),
        ];
    }
}
