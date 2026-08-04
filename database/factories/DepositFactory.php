<?php

namespace Database\Factories;

use App\Enums\DepositStatus;
use App\Models\Deposit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deposit>
 */
class DepositFactory extends Factory
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
            'phone' => fake()->phoneNumber(),
            'amount' => 50000,
            'currency' => 'CHF',
            'status' => DepositStatus::Pending,
            'provider' => 'stripe',
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DepositStatus::Paid,
            'provider_reference' => 'cs_test_'.fake()->uuid(),
            'paid_at' => now(),
        ]);
    }
}
