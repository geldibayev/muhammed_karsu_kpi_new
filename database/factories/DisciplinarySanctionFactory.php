<?php

namespace Database\Factories;

use App\Models\DisciplinarySanction;
use App\Models\DisciplinarySanctionImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DisciplinarySanction>
 */
class DisciplinarySanctionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hemis_id' => (string) fake()->unique()->numberBetween(1_000_000_000, 9_999_999_999),
            'import_id' => DisciplinarySanctionImport::factory(),
            'source_row' => fake()->numberBetween(2, 1000),
        ];
    }
}
