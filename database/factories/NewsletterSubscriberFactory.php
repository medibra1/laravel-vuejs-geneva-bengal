<?php

namespace Database\Factories;

use App\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NewsletterSubscriber>
 */
class NewsletterSubscriberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'unsubscribe_token' => Str::random(48),
            'unsubscribed_at' => null,
        ];
    }

    public function unsubscribed(): static
    {
        return $this->state(fn (): array => ['unsubscribed_at' => now()]);
    }
}
