<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            UniversitySeeder::class,
            OptionSeeder::class,
            LanguageSeeder::class,
            CriterionSeeder::class,
            CriterionReviewerAssignmentSeeder::class,
            CriterionManualScoreOptionSeeder::class,
            DepartmentSeeder::class,
        ]);
    }
}
