<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'gamertag' => 'VKR_'.strtoupper(Str::random(8)),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('Password123!'),
            'role' => User::ROLE_PLAYER,
            'elo_rating' => 1500,
            'preferred_class' => fake()->randomElement(User::ALLOWED_CLASSES),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => User::ROLE_ADMIN,
        ]);
    }

    public function organizer(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => User::ROLE_ORGANIZER,
        ]);
    }

    public function player(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => User::ROLE_PLAYER,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }
}
