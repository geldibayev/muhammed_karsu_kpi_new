<?php

namespace Database\Seeders;

use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Observance;
use App\Models\Option;
use Illuminate\Database\Seeder;

class OptionSeeder extends Seeder
{
    public function run(): void
    {
        $evaluations = [
            'hold_degrees' => [
                'uz' => 'Ilmiy daraja yoki unvonga ega bo‘lganlar',
                'kaa' => 'Ilimiy dáreje yaki ataqqa iye bolganlar',
                'ru' => 'Имеющие ученую степень или звание',
                'en' => 'Holding academic degrees or titles',
            ],
            'no_degrees' => [
                'uz' => 'Ilmiy daraja yoki unvonga ega bo‘lmaganlar',
                'kaa' => 'Ilimiy dáreje yamasa ataqqa ie bolmaǵanlar',
                'ru' => 'Не имеющие ученой степени или звания',
                'en' => 'No academic degree or title',
            ],
            'foreign_lang' => [
                'uz' => 'Fakultetlararo chet tillari kafedrasi',
                'kaa' => 'Fakultetler aralıq shet tilleri kafedrası',
                'ru' => 'Межфакультетская кафедра иностранных языков',
                'en' => 'Interfaculty Department of Foreign Languages',
            ],
            'physical' => [
                'uz' => 'Fakultetlararo jismoniy tarbiya kafedrasi',
                'kaa' => 'Fakultetler aralıq dene tárbiyası kafedrası',
                'ru' => 'Межфакультетская кафедра физической культуры',
                'en' => 'Department of Interfaculty Physical Education',
            ],
        ];

        foreach ($evaluations as $code => $name) {
            Evaluation::query()->updateOrCreate(['code' => $code], ['name' => $name, 'status' => '1']);
        }

        $observances = [
            'current' => ['Joriy o‘quv yili uchun', 'Usı oqıw jılı ushın', 'На текущий учебный год', 'For the current academic year'],
            'previous' => ['Avvalgi o‘quv yili uchun', 'Aldıńǵı oqıw jılı ushın', 'За предыдущий учебный год', 'For the previous academic year'],
            'current_state' => ['Joriy holati bo‘yicha', 'Házirgi jaǵdayı boyınsha', 'По текущему состоянию', 'Based on the current state'],
            'certificate_expire' => ['Sertifikat muddati tugagunga qadar', 'Sertifikat múddeti tamamlanǵansha', 'До истечения срока действия сертификата', 'Until the certificate expires'],
            'last3years' => ['Oxirgi 3 yilda', 'Sońǵı 3 jılda', 'За последние 3 года', 'Last 3 years'],
            'project_finished' => ['Loyiha tugagunga qadar', 'Joybar juwmaqlanǵansha', 'До завершения проекта', 'Until project completion'],
            'end_of_council' => ['Kengashda faoliyati tugagunga qadar', 'Keńestegi xızmeti juwmaqlanǵansha', 'До окончания срока деятельности в Совете', 'Until the end of their term of office in the Council'],
        ];

        foreach ($observances as $code => $translations) {
            Observance::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => [
                        'uz' => $translations[0],
                        'kaa' => $translations[1],
                        'ru' => $translations[2],
                        'en' => $translations[3],
                    ],
                    'status' => '1',
                ],
            );
        }

        Option::query()->updateOrCreate(['key' => 'title'], ['value' => 'KarSU KPI']);

        $formulas = [
            Formula::Competition => ['Raqobat reyting tizimida', 'Báseki reyting sistemasında', 'В рейтинговой системе конкуренции', 'Competition in the rating system'],
            Formula::Maximum => ['Maksimal ballga asoslangan', 'Maksimal ballǵa tiykarlanǵan', 'На основе максимального балла', 'Based on maximum score'],
            Formula::Unlimited => ['Cheklanmagan ball asosida', 'Sheklenbegen ball tiykarında', 'На основе неограниченных баллов', 'Unlimited points'],
        ];

        foreach ($formulas as $code => $translations) {
            Formula::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => [
                        'uz' => $translations[0],
                        'kaa' => $translations[1],
                        'ru' => $translations[2],
                        'en' => $translations[3],
                    ],
                    'status' => '1',
                ],
            );
        }
    }
}
