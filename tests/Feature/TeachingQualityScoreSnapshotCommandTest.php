<?php

namespace Tests\Feature;

use App\Actions\ApplyTeachingQualityScoreSnapshot;
use App\Models\AcademicDegree;
use App\Models\AcademicRank;
use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Datum;
use App\Models\Department;
use App\Models\EmployeeStatus;
use App\Models\EmployeeType;
use App\Models\EmploymentForm;
use App\Models\EmploymentStaff;
use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Point;
use App\Models\Report;
use App\Models\StaffPosition;
use App\Models\User;
use App\Models\Workplace;
use App\Support\TeachingQualityScoreSnapshot;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeachingQualityScoreSnapshotCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_snapshot_matches_the_validated_data(): void
    {
        $rows = TeachingQualityScoreSnapshot::rows();

        $this->assertCount(1366, $rows);
        $this->assertCount(1366, array_unique(array_column($rows, 'hemis_id')));
        $this->assertSame(['hemis_id' => '3461811014', 'point' => '10.00'], $rows[0]);
        $this->assertSame(['hemis_id' => '3522111008', 'point' => '9.61'], $rows[1364]);
        $this->assertSame(['hemis_id' => '3862012025', 'point' => '9.46'], $rows[1365]);
        $this->assertSame(
            'built-in:teaching-quality-correction-2026-08-27',
            TeachingQualityScoreSnapshot::provenance('3862012025')['source'],
        );
        $this->assertSame(
            '044556ad1eaee2c19567b552ec048ce10a963460761ee74191607398b198a334',
            TeachingQualityScoreSnapshot::provenance('3461811014')['data_sha256'],
        );
        $this->assertSame(
            'e24a1b3a9610fa9770f8c16277b92a57525291000e39572835e230aee7cd10db',
            TeachingQualityScoreSnapshot::SOURCE_SHA256,
        );
        $this->assertSame(
            TeachingQualityScoreSnapshot::DATA_SHA256,
            hash('sha256', implode("\n", array_map(
                fn (array $row): string => $row['hemis_id'].'|'.$row['point'],
                $rows,
            ))),
        );
    }

    public function test_command_dry_runs_then_applies_scores_and_is_idempotent(): void
    {
        [$report, $criterion] = $this->criterion();
        $perfectScore = User::factory()->create(['hemis_id' => 3461811014]);
        $otherScore = User::factory()->create(['hemis_id' => 3462011201]);
        $correctedScore = User::factory()->create(['hemis_id' => 3862012025]);

        $this->artisan('kpi:criteria:apply-teaching-quality-snapshot', [
            'report' => $report->getKey(),
        ])
            ->expectsOutputToContain('DRY RUN')
            ->assertSuccessful();
        $this->assertDatabaseCount('data', 0);

        $this->artisan('kpi:criteria:apply-teaching-quality-snapshot', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])
            ->expectsOutputToContain('APPLIED')
            ->assertSuccessful();

        $this->assertSame(10.0, $this->datum($perfectScore, $criterion)->point);
        $this->assertSame(9.54, $this->datum($otherScore, $criterion)->point);
        $this->assertSame(9.46, $this->datum($correctedScore, $criterion)->point);
        $this->assertSame(
            'built-in:teaching-quality-correction-2026-08-27',
            $this->datum($correctedScore, $criterion)->material['source'],
        );
        $this->assertSame(10.0, $this->point($perfectScore, $criterion));
        $this->assertSame(9.54, $this->point($otherScore, $criterion));
        $this->assertSame(9.46, $this->point($correctedScore, $criterion));
        $this->assertDatabaseCount('datum_histories', 3);
        $historyCount = $this->datum($perfectScore, $criterion)->histories()->count();

        $this->artisan('kpi:criteria:apply-teaching-quality-snapshot', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertDatabaseCount('data', 3);
        $this->assertSame($historyCount, $this->datum($perfectScore, $criterion)->histories()->count());
        $this->assertSame(3, Datum::query()->where('system_key', ApplyTeachingQualityScoreSnapshot::SYSTEM_KEY)->count());
    }

    public function test_large_import_chunks_both_projection_upserts(): void
    {
        [$report] = $this->criterion();
        $snapshotRows = collect(TeachingQualityScoreSnapshot::rows())->take(501)->values();
        User::factory()
            ->count($snapshotRows->count())
            ->sequence(fn (Sequence $sequence): array => [
                'hemis_id' => (int) $snapshotRows[$sequence->index]['hemis_id'],
            ])
            ->create();
        $queries = collect();
        DB::listen(function (QueryExecuted $query) use ($queries): void {
            $queries->push($query->sql);
        });

        $this->artisan('kpi:criteria:apply-teaching-quality-snapshot', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertCount(2, $queries->filter(
            fn (string $sql): bool => str_contains($sql, 'insert into "criterion_points"')
                || str_contains($sql, 'insert into `criterion_points`'),
        ));
        $this->assertCount(2, $queries->filter(
            fn (string $sql): bool => str_contains($sql, 'insert into "points"')
                || str_contains($sql, 'insert into `points`'),
        ));
    }

    public function test_command_rejects_wrong_report_or_criterion_configuration(): void
    {
        [$report, $criterion] = $this->criterion();
        $report->update(['code' => '2024-2025']);

        $this->artisan('kpi:criteria:apply-teaching-quality-snapshot', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])
            ->expectsOutput('Snapshot faqat 2025-2026 hisobotiga tegishli.')
            ->assertFailed();

        $report->update(['code' => TeachingQualityScoreSnapshot::REPORT_CODE]);
        $criterion->update(['checking' => 'manual']);

        $this->artisan('kpi:criteria:apply-teaching-quality-snapshot', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])
            ->expectsOutputToContain('Renumber migratsiyasini tekshiring')
            ->assertFailed();

        $this->assertDatabaseCount('data', 0);
    }

    public function test_command_stops_when_another_resource_would_duplicate_the_score(): void
    {
        [$report, $criterion] = $this->criterion();
        $user = User::factory()->create(['hemis_id' => 3461811014]);
        Datum::query()->create([
            'name' => 'Oldingi tashqi resurs',
            'user_id' => $user->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'accepted',
            'point' => 8,
        ]);

        $this->artisan('kpi:criteria:apply-teaching-quality-snapshot', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])
            ->expectsOutput('1.5 mezonida 1 ta boshqa resurs bor. Ikki marta ball bermaslik uchun import to‘xtatildi.')
            ->assertFailed();

        $this->assertSame(1, Datum::query()->count());
        $this->assertDatabaseMissing('data', [
            'user_id' => $user->getKey(),
            'criterion_id' => $criterion->getKey(),
            'system_key' => ApplyTeachingQualityScoreSnapshot::SYSTEM_KEY,
        ]);
    }

    public function test_command_removes_a_stale_system_score(): void
    {
        [$report, $criterion] = $this->criterion();
        $staleUser = User::factory()->create(['hemis_id' => 9999999999]);
        $datum = Datum::query()->create([
            'name' => 'Eski tizim balli',
            'user_id' => $staleUser->getKey(),
            'criterion_id' => $criterion->getKey(),
            'system_key' => ApplyTeachingQualityScoreSnapshot::SYSTEM_KEY,
            'status' => 'accepted',
            'point' => 7,
        ]);

        $this->artisan('kpi:criteria:apply-teaching-quality-snapshot', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame('deleted', $datum->fresh()->status);
        $this->assertSame(0.0, $datum->fresh()->point);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->getKey(),
            'message_type' => 'teaching_quality_score_removed',
        ]);
    }

    public function test_command_fills_missing_scores_from_each_department_average(): void
    {
        [$report, $criterion] = $this->criterion();
        $faculty = $this->department(9_100, 'Fakultet');
        $firstDepartment = $this->department(9_101, 'Birinchi kafedra', $faculty);
        $secondDepartment = $this->department(9_102, 'Ikkinchi kafedra', $faculty);
        $departmentWithoutScores = $this->department(9_103, 'Ballsiz kafedra', $faculty);
        $firstSource = User::factory()->create(['hemis_id' => 3461811014]);
        $secondSource = User::factory()->create(['hemis_id' => 3462011201]);
        $thirdSource = User::factory()->create(['hemis_id' => 3932312002]);
        $firstMissing = User::factory()->create(['hemis_id' => 9000000001]);
        $secondMissing = User::factory()->create(['hemis_id' => 9000000002]);
        $alreadyScored = User::factory()->create(['hemis_id' => 9000000003]);
        $withoutDepartmentAverage = User::factory()->create(['hemis_id' => 9000000004]);
        $inactive = User::factory()->create(['hemis_id' => 9000000005, 'status' => '0']);

        foreach ([$firstSource, $secondSource, $firstMissing, $alreadyScored] as $user) {
            $this->workplace($user, $firstDepartment);
        }

        foreach ([$thirdSource, $secondMissing] as $user) {
            $this->workplace($user, $secondDepartment);
        }

        $this->workplace($withoutDepartmentAverage, $departmentWithoutScores);
        $this->workplace($inactive, $firstDepartment);
        Datum::query()->create([
            'name' => 'Oldindan berilgan ball',
            'user_id' => $alreadyScored->getKey(),
            'criterion_id' => $criterion->getKey(),
            'system_key' => 'existing-teaching-quality-score',
            'status' => 'accepted',
            'point' => 8,
        ]);

        $arguments = [
            'report' => $report->getKey(),
            '--fill-department-averages' => true,
        ];

        $this->artisan('kpi:criteria:apply-teaching-quality-snapshot', $arguments)
            ->expectsOutputToContain('DRY RUN')
            ->assertSuccessful();
        $this->assertDatabaseMissing('data', [
            'system_key' => ApplyTeachingQualityScoreSnapshot::DEPARTMENT_AVERAGE_SYSTEM_KEY,
        ]);

        $this->artisan('kpi:criteria:apply-teaching-quality-snapshot', [
            ...$arguments,
            '--apply' => true,
        ])->assertSuccessful();

        $firstAverageDatum = $this->departmentAverageDatum($firstMissing, $criterion);
        $secondAverageDatum = $this->departmentAverageDatum($secondMissing, $criterion);
        $this->assertSame(9.77, $firstAverageDatum->point);
        $this->assertSame(9.04, $secondAverageDatum->point);
        $this->assertSame(2, $firstAverageDatum->material['source_count']);
        $this->assertSame($firstDepartment->getKey(), $firstAverageDatum->material['department_id']);
        $this->assertSame(9.77, $this->point($firstMissing, $criterion));
        $this->assertSame(9.04, $this->point($secondMissing, $criterion));
        $this->assertSame(8.0, $this->point($alreadyScored, $criterion));
        $this->assertDatabaseMissing('data', [
            'user_id' => $withoutDepartmentAverage->getKey(),
            'system_key' => ApplyTeachingQualityScoreSnapshot::DEPARTMENT_AVERAGE_SYSTEM_KEY,
        ]);
        $this->assertDatabaseMissing('data', [
            'user_id' => $inactive->getKey(),
            'system_key' => ApplyTeachingQualityScoreSnapshot::DEPARTMENT_AVERAGE_SYSTEM_KEY,
        ]);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $firstAverageDatum->getKey(),
            'message_type' => 'teaching_quality_department_average_assigned',
        ]);
        $historyCount = $firstAverageDatum->histories()->count();

        $this->artisan('kpi:criteria:apply-teaching-quality-snapshot', [
            ...$arguments,
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame(2, Datum::query()
            ->where('system_key', ApplyTeachingQualityScoreSnapshot::DEPARTMENT_AVERAGE_SYSTEM_KEY)
            ->count());
        $this->assertSame($historyCount, $firstAverageDatum->histories()->count());
    }

    /** @return array{Report, Criterion} */
    private function criterion(): array
    {
        $evaluation = Evaluation::query()->firstOrCreate([
            'code' => 'no_degrees',
        ], [
            'name' => ['uz' => 'Ilmiy darajasiz'],
            'status' => '1',
        ]);
        $formula = Formula::query()->firstOrCreate([
            'code' => Formula::Maximum,
        ], [
            'name' => ['uz' => 'Maksimal'],
            'status' => '1',
        ]);
        $report = Report::query()->create([
            'code' => TeachingQualityScoreSnapshot::REPORT_CODE,
            'name' => ['uz' => '2025-2026'],
            'status' => '1',
        ]);
        $parent = Criterion::query()->create([
            'code' => '1',
            'name' => ['uz' => 'O‘quv-uslubiy ishlar'],
            'report_id' => $report->getKey(),
            'upload' => '0',
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => ApplyTeachingQualityScoreSnapshot::CRITERION_CODE,
            'name' => ['uz' => 'O‘qitish sifati darajasi'],
            'parent_id' => $parent->getKey(),
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
            'checking' => 'hemis:vote',
            'upload' => '0',
            'status' => '1',
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->getKey(),
            'evaluation' => $evaluation->code,
            'has' => '1',
            'score' => 10,
        ]);

        return [$report, $criterion];
    }

    private function datum(User $user, Criterion $criterion): Datum
    {
        return Datum::query()
            ->whereBelongsTo($user)
            ->whereBelongsTo($criterion)
            ->where('system_key', ApplyTeachingQualityScoreSnapshot::SYSTEM_KEY)
            ->firstOrFail();
    }

    private function point(User $user, Criterion $criterion): float
    {
        return (float) Point::query()
            ->whereBelongsTo($user)
            ->whereBelongsTo($criterion)
            ->where('report_id', $criterion->report_id)
            ->value('point');
    }

    private function department(int $id, string $name, ?Department $parent = null): Department
    {
        return Department::query()->create([
            'id' => $id,
            'name' => ['uz' => $name, 'kaa' => $name, 'ru' => $name, 'en' => $name],
            'parent_id' => $parent?->getKey(),
            'status' => '1',
        ]);
    }

    private function workplace(User $user, Department $department): Workplace
    {
        $academicDegree = AcademicDegree::query()->firstOrCreate(['id' => 9_201], ['name' => 'PhD']);
        $academicRank = AcademicRank::query()->firstOrCreate(['id' => 9_202], ['name' => 'Dotsent']);
        $form = EmploymentForm::query()->firstOrCreate([
            'id' => EmploymentForm::PRIMARY_WORKPLACE_ID,
        ], ['name' => 'Asosiy ish joyi']);
        $staff = EmploymentStaff::query()->firstOrCreate(['id' => 9_203], ['name' => '1 stavka']);
        $position = StaffPosition::query()->firstOrCreate(['id' => 9_204], ['name' => 'O‘qituvchi']);
        $status = EmployeeStatus::query()->firstOrCreate(['id' => 9_205], ['name' => 'Ishlamoqda']);
        $type = EmployeeType::query()->firstOrCreate(['id' => 9_206], ['name' => 'Xodim']);

        return Workplace::query()->create([
            'user_id' => $user->getKey(),
            'department_id' => $department->getKey(),
            'academic_degree_id' => $academicDegree->getKey(),
            'academic_rank_id' => $academicRank->getKey(),
            'form_id' => $form->getKey(),
            'staff_id' => $staff->getKey(),
            'staff_position_id' => $position->getKey(),
            'status_id' => $status->getKey(),
            'type_id' => $type->getKey(),
        ]);
    }

    private function departmentAverageDatum(User $user, Criterion $criterion): Datum
    {
        return Datum::query()
            ->whereBelongsTo($user)
            ->whereBelongsTo($criterion)
            ->where('system_key', ApplyTeachingQualityScoreSnapshot::DEPARTMENT_AVERAGE_SYSTEM_KEY)
            ->firstOrFail();
    }
}
