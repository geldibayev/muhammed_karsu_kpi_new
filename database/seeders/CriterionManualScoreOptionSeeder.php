<?php

namespace Database\Seeders;

use App\Models\Criterion;
use App\Models\CriterionManualScoreOption;
use Illuminate\Database\Seeder;
use RuntimeException;

class CriterionManualScoreOptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $options = [
            ['criterion' => '1.1', 'code' => 'video_lesson', 'label' => 'Videodars', 'point' => 1.50, 'sort_order' => 1],
            ['criterion' => '1.1', 'code' => 'video_clip', 'label' => 'Videorolik', 'point' => 1.00, 'sort_order' => 2],
            ['criterion' => '1.1', 'code' => 'presentation', 'label' => 'Taqdimot', 'point' => 0.50, 'sort_order' => 3],
            ['criterion' => '2.1.3', 'code' => 'a1', 'label' => 'A1 sertifikat', 'point' => 0.50, 'sort_order' => 1],
            ['criterion' => '2.1.3', 'code' => 'a2', 'label' => 'A2 sertifikat', 'point' => 0.50, 'sort_order' => 2],
            ['criterion' => '2.1.3', 'code' => 'b1', 'label' => 'B1 sertifikat', 'point' => 0.75, 'sort_order' => 3],
            ['criterion' => '2.1.3', 'code' => 'b2', 'label' => 'B2 sertifikat', 'point' => 1.00, 'sort_order' => 4],
            ['criterion' => '2.1.3', 'code' => 'c1', 'label' => 'C1 sertifikat', 'point' => 1.50, 'sort_order' => 5],
            ['criterion' => '2.1.3', 'code' => 'c2', 'label' => 'C2 sertifikat', 'point' => 2.00, 'sort_order' => 6],
            ['criterion' => '2.1.4', 'code' => 'rector_order', 'label' => 'Rektor buyrug‘i bilan tasdiqlangan loyiha', 'point' => 1.00, 'sort_order' => 1],
            ['criterion' => '3.1.6', 'code' => 'dsc_diploma', 'label' => 'OAK tasdiqlagan DSc diplomi', 'point' => 3.00, 'sort_order' => 1],
            ['criterion' => '3.1.7', 'code' => 'phd_diploma', 'label' => 'OAK tasdiqlagan PhD diplomi', 'point' => 3.00, 'sort_order' => 1],
        ];

        $criteria = Criterion::query()
            ->whereHas('report', fn ($query) => $query->where('status', '1'))
            ->whereIn('code', array_column($options, 'criterion'))
            ->get(['id', 'code'])
            ->keyBy('code');

        foreach ($options as $option) {
            $criterion = $criteria->get($option['criterion']);

            if (! $criterion instanceof Criterion) {
                throw new RuntimeException("{$option['criterion']} mezoni topilmadi.");
            }

            CriterionManualScoreOption::query()->updateOrCreate(
                [
                    'criterion_id' => $criterion->getKey(),
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
