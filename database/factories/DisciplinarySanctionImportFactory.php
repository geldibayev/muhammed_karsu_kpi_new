<?php

namespace Database\Factories;

use App\Models\DisciplinarySanctionImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DisciplinarySanctionImport>
 */
class DisciplinarySanctionImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_file' => 'hemis_id.xlsx',
            'source_hash' => hash('sha256', fake()->uuid()),
            'row_count' => 1,
            'imported_at' => now(),
        ];
    }
}
