<?php

namespace Database\Seeders;

use App\Models\CriterionManualScoreOption;
use Illuminate\Database\Seeder;

class CriterionManualScoreOptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $options = [
            ['criterion_id' => 2, 'code' => 'video_lesson', 'label' => 'Videodars', 'point' => 1.50, 'sort_order' => 1],
            ['criterion_id' => 2, 'code' => 'video_clip', 'label' => 'Videorolik', 'point' => 1.00, 'sort_order' => 2],
            ['criterion_id' => 2, 'code' => 'presentation', 'label' => 'Taqdimot', 'point' => 0.50, 'sort_order' => 3],
            ['criterion_id' => 15, 'code' => 'a1', 'label' => 'A1 sertifikat', 'point' => 0.50, 'sort_order' => 1],
            ['criterion_id' => 15, 'code' => 'a2', 'label' => 'A2 sertifikat', 'point' => 0.50, 'sort_order' => 2],
            ['criterion_id' => 15, 'code' => 'b1', 'label' => 'B1 sertifikat', 'point' => 0.75, 'sort_order' => 3],
            ['criterion_id' => 15, 'code' => 'b2', 'label' => 'B2 sertifikat', 'point' => 1.00, 'sort_order' => 4],
            ['criterion_id' => 15, 'code' => 'c1', 'label' => 'C1 sertifikat', 'point' => 1.50, 'sort_order' => 5],
            ['criterion_id' => 15, 'code' => 'c2', 'label' => 'C2 sertifikat', 'point' => 2.00, 'sort_order' => 6],
            ['criterion_id' => 16, 'code' => 'rector_order', 'label' => 'Rektor buyrug‘i bilan tasdiqlangan loyiha', 'point' => 1.00, 'sort_order' => 1],
            ['criterion_id' => 25, 'code' => 'dsc_diploma', 'label' => 'OAK tasdiqlagan DSc diplomi', 'point' => 1.00, 'sort_order' => 1],
            ['criterion_id' => 26, 'code' => 'phd_diploma', 'label' => 'OAK tasdiqlagan PhD diplomi', 'point' => 1.00, 'sort_order' => 1],
        ];

        foreach ($options as $option) {
            CriterionManualScoreOption::query()->updateOrCreate(
                [
                    'criterion_id' => $option['criterion_id'],
                    'code' => $option['code'],
                ],
                [
                    'label' => ['uz' => $option['label']],
                    'point' => $option['point'],
                    'sort_order' => $option['sort_order'],
                    'active' => true,
                ],
            );
        }
    }
}
