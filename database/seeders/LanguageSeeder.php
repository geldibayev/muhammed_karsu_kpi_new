<?php

namespace Database\Seeders;

use App\Models\Report;
use App\Models\Year;
use App\Models\Language;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        Report::create([
            'name' => [
                'uz' => '2025-2026',
                'kaa' => '2025-2026',
                'ru' => '2025-2026',
                'en' => '2025-2026',
            ],
            'status' => '1',
        ]);

        $languages = [
            [
                'id' => 11,
                'name' => [
                    'uz' => "O‘zbek",
                    'kaa' => "O‘zbek",
                    'ru' => "O‘zbek",
                    'en' => "O‘zbek",
                ],
                'status' => '1',
            ],
            [
                'id' => 12,
                'name' => [
                    'uz' => "Rus",
                    'kaa' => "Rus",
                    'ru' => "Rus",
                    'en' => "Rus",
                ],
                'status' => '1',
            ],
            [
                'id' => 13,
                'name' => [
                    'uz' => "Qoraqalpoq",
                    'kaa' => "Qoraqalpoq",
                    'ru' => "Qoraqalpoq",
                    'en' => "Qoraqalpoq",
                ],
                'status' => '1',
            ],
            [
                'id' => 14,
                'name' => [
                    'uz' => "Ingliz",
                    'kaa' => "Ingliz",
                    'ru' => "Ingliz",
                    'en' => "Ingliz",
                ],
                'status' => '1',
            ],
            [
                'id' => 15,
                'name' => [
                    'uz' => "Tojik",
                    'kaa' => "Tojik",
                    'ru' => "Tojik",
                    'en' => "Tojik",
                ],
                'status' => '1',
            ],
            [
                'id' => 16,
                'name' => [
                    'uz' => "Qozoq",
                    'kaa' => "Qozoq",
                    'ru' => "Qozoq",
                    'en' => "Qozoq",
                ],
                'status' => '1',
            ],
            [
                'id' => 17,
                'name' => [
                    'uz' => "Turkman",
                    'kaa' => "Turkman",
                    'ru' => "Turkman",
                    'en' => "Turkman",
                ],
                'status' => '1',
            ],
            [
                'id' => 18,
                'name' => [
                    'uz' => "Koreys",
                    'kaa' => "Koreys",
                    'ru' => "Koreys",
                    'en' => "Koreys",
                ],
                'status' => '1',
            ],
            [
                'id' => 19,
                'name' => [
                    'uz' => "Nemis",
                    'kaa' => "Nemis",
                    'ru' => "Nemis",
                    'en' => "Nemis",
                ],
                'status' => '1',
            ],
            [
                'id' => 20,
                'name' => [
                    'uz' => "Frantsuz",
                    'kaa' => "Frantsuz",
                    'ru' => "Frantsuz",
                    'en' => "Frantsuz",
                ],
                'status' => '1',
            ],
            [
                'id' => 21,
                'name' => [
                    'uz' => "Turk",
                    'kaa' => "Turk",
                    'ru' => "Turk",
                    'en' => "Turk",
                ],
                'status' => '1',
            ],
            [
                'id' => 22,
                'name' => [
                    'uz' => "Qirg'iz",
                    'kaa' => "Qirg'iz",
                    'ru' => "Qirg'iz",
                    'en' => "Qirg'iz",
                ],
                'status' => '1',
            ],
        ];
        foreach ($languages as $language) {
            Language::create($language);
        }

        $years = [
            [
                'id' => 2018,
                'name' => "2018-2019",
                'status' => '1',
            ],
            [
                'id' => 2019,
                'name' => "2019-2020",
                'status' => '1',
            ],
            [
                'id' => 2020,
                'name' => "2020-2021",
                'status' => '1',
            ],
            [
                'id' => 2021,
                'name' => "2021-2022",
                'status' => '1',
            ],
            [
                'id' => 2022,
                'name' => "2022-2023",
                'status' => '1',
            ],
            [
                'id' => 2023,
                'name' => "2023-2024",
                'status' => '1',
            ],
            [
                'id' => 2024,
                'name' => "2024-2025",
                'status' => '1',
            ],
            [
                'id' => 2025,
                'name' => "2025-2026",
                'status' => '1',
            ],
            [
                'id' => 2026,
                'name' => "2026-2027",
                'status' => '1',
            ],
            [
                'id' => 2027,
                'name' => "2027-2028",
                'status' => '1',
            ],
            [
                'id' => 2028,
                'name' => "2028-2029",
                'status' => '1',
            ],
            [
                'id' => 2029,
                'name' => "2029-2030",
                'status' => '1',
            ],
            [
                'id' => 2030,
                'name' => "2030-2031",
                'status' => '1',
            ],
        ];
        foreach ($years as $year) {
            Year::create($year);
        }
    }
}
