<?php

namespace Database\Seeders;

use App\Actions\ImportDisciplinarySanctions;
use Illuminate\Database\Seeder;

class DisciplinarySanctionSeeder extends Seeder
{
    public function run(ImportDisciplinarySanctions $importDisciplinarySanctions): void
    {
        $result = $importDisciplinarySanctions->handleBuiltIn(apply: true);

        $this->command?->info(sprintf(
            'Intizomiy jazo ro‘yxati: %d ta ID, tizimdagi %d ta foydalanuvchi, %d ta o‘zgargan ball.',
            $result['rows'],
            $result['existing_users'],
            $result['changed_scores'],
        ));
    }
}
