<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => fake()->unique()->numberBetween(10_000, 9_999_999),
            'hemis_id' => fake()->unique()->numberBetween(10_000, 9_999_999),
            'name' => [
                'full' => fake()->name(),
                'first' => fake()->firstName(),
                'last' => fake()->lastName(),
                'third' => '',
                'short' => fake()->lastName(),
            ],
            'image' => null,
            'pos' => 'user',
            'rol' => ['teacher'],
            'status' => '1',
            'degree' => 'no_degrees',
        ];
    }

    public function withRole(string $role): static
    {
        return $this->state(fn (): array => [
            'rol' => [$role],
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn (): array => [
            'rol' => ['super_admin', 'teacher'],
        ]);
    }
}
