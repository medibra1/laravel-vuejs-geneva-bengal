<?php

namespace Database\Factories;

use App\Enums\ContactReason;
use App\Enums\ContactStatus;
use App\Models\ContactRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactRequest>
 */
class ContactRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'reason' => fake()->randomElement(ContactReason::cases()),
            'city' => fake()->city(),
            'message' => fake()->paragraph(),
            'status' => ContactStatus::New,
        ];
    }
}
