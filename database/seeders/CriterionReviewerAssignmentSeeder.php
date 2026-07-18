<?php

namespace Database\Seeders;

use App\Models\CriterionReviewerAssignment;
use Illuminate\Database\Seeder;

class CriterionReviewerAssignmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();
        $assignments = [
            ['hemis_id' => 3172011004, 'criterion_id' => 2, 'criterion_code' => '1/2'],
            ['hemis_id' => 3172011004, 'criterion_id' => 8, 'criterion_code' => '1/8'],
            ['hemis_id' => 3862011037, 'criterion_id' => 6, 'criterion_code' => '1/6'],
            ['hemis_id' => 3462011207, 'criterion_id' => 23, 'criterion_code' => '3/23'],
            ['hemis_id' => 3462011207, 'criterion_id' => 25, 'criterion_code' => '3/25'],
            ['hemis_id' => 3462011207, 'criterion_id' => 26, 'criterion_code' => '3/26'],
            ['hemis_id' => 3862311015, 'criterion_id' => 7, 'criterion_code' => '1/7'],
            ['hemis_id' => 3862311015, 'criterion_id' => 15, 'criterion_code' => '2/15'],
            ['hemis_id' => 3461612013, 'criterion_id' => 41, 'criterion_code' => '4/41'],
        ];

        CriterionReviewerAssignment::query()->upsert(
            array_map(
                static fn (array $assignment): array => [
                    ...$assignment,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $assignments,
            ),
            ['criterion_id'],
            ['hemis_id', 'criterion_code', 'updated_at'],
        );
    }
}
