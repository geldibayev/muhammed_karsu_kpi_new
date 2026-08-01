<?php

use App\Support\KpiCriterionSpecification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $this->upsertReferenceCodes();
            $this->backfillReports();
            $this->backfillCriterionCodes();
            $this->applyApprovedSpecification();
        }, 3);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('criterion_reviewer_assignments')
            ->orderBy('id')
            ->get(['id', 'criterion_id'])
            ->each(function (object $assignment): void {
                $criterion = DB::table('criteria')->find($assignment->criterion_id, ['id', 'parent_id', 'report_id']);

                if ($criterion === null || $criterion->parent_id === null) {
                    return;
                }

                $sectionNumber = DB::table('criteria')
                    ->where('report_id', $criterion->report_id)
                    ->whereNull('parent_id')
                    ->where('id', '<=', $criterion->parent_id)
                    ->count();

                DB::table('criterion_reviewer_assignments')
                    ->where('id', $assignment->id)
                    ->update(['criterion_code' => $sectionNumber.'/'.$criterion->id]);
            });
    }

    private function upsertReferenceCodes(): void
    {
        $formulaCodesByUzName = [
            'Raqobat reyting tizimida' => KpiCriterionSpecification::Competition,
            'Maksimal ballga asoslangan' => KpiCriterionSpecification::Maximum,
            'Cheklanmagan ball asosida' => KpiCriterionSpecification::Unlimited,
        ];

        DB::table('formulas')->orderBy('id')->get(['id', 'name'])->each(
            function (object $formula) use ($formulaCodesByUzName): void {
                $name = json_decode($formula->name, true);
                $code = $formulaCodesByUzName[data_get($name, 'uz')] ?? null;

                if ($code !== null) {
                    DB::table('formulas')->where('id', $formula->id)->update(['code' => $code]);
                }
            },
        );

        $observances = [
            'previous' => [
                'uz' => 'Avvalgi o‘quv yili uchun',
                'kaa' => 'Aldıńǵı oqıw jılı ushın',
                'ru' => 'За предыдущий учебный год',
                'en' => 'For the previous academic year',
            ],
            'current_state' => [
                'uz' => 'Joriy holati bo‘yicha',
                'kaa' => 'Házirgi jaǵdayı boyınsha',
                'ru' => 'По текущему состоянию',
                'en' => 'Based on the current state',
            ],
        ];

        foreach ($observances as $code => $name) {
            DB::table('observances')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => json_encode($name, JSON_UNESCAPED_UNICODE),
                    'status' => '1',
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }

    private function backfillReports(): void
    {
        $usedCodes = [];

        DB::table('reports')->orderBy('id')->get(['id', 'name', 'status'])->each(
            function (object $report) use (&$usedCodes): void {
                $name = json_decode($report->name, true);
                $baseCode = Str::slug((string) data_get($name, 'uz', 'report-'.$report->id));
                $baseCode = $baseCode !== '' ? $baseCode : 'report-'.$report->id;
                $code = isset($usedCodes[$baseCode]) ? $baseCode.'-'.$report->id : $baseCode;
                $usedCodes[$code] = true;

                $attributes = ['code' => $code];

                if ($report->status === '1') {
                    $attributes['starts_on'] = config('kpi.report_period_start');
                    $attributes['ends_on'] = config('kpi.report_period_end');
                }

                DB::table('reports')->where('id', $report->id)->update($attributes);
            },
        );
    }

    private function backfillCriterionCodes(): void
    {
        DB::table('reports')->orderBy('id')->pluck('id')->each(function (int $reportId): void {
            $parents = DB::table('criteria')
                ->where('report_id', $reportId)
                ->whereNull('parent_id')
                ->orderBy('id')
                ->get(['id']);

            foreach ($parents as $parentIndex => $parent) {
                $section = $parentIndex + 1;
                DB::table('criteria')->where('id', $parent->id)->update([
                    'code' => (string) $section,
                    'sort_order' => $section,
                ]);

                $children = DB::table('criteria')
                    ->where('parent_id', $parent->id)
                    ->orderBy('id')
                    ->get(['id']);

                foreach ($children as $childIndex => $child) {
                    $position = $childIndex + 1;
                    $code = $section === 1
                        ? "1.{$position}"
                        : "{$section}.1.{$position}";

                    DB::table('criteria')->where('id', $child->id)->update([
                        'code' => $code,
                        'sort_order' => $position,
                    ]);
                }
            }
        });

        DB::table('criterion_reviewer_assignments')
            ->orderBy('id')
            ->get(['id', 'criterion_id'])
            ->each(function (object $assignment): void {
                $code = DB::table('criteria')->where('id', $assignment->criterion_id)->value('code');

                if (is_string($code) && $code !== '') {
                    DB::table('criterion_reviewer_assignments')
                        ->where('id', $assignment->id)
                        ->update(['criterion_code' => $code]);
                }
            });
    }

    private function applyApprovedSpecification(): void
    {
        $reportId = DB::table('reports')->where('status', '1')->orderByDesc('id')->value('id');

        if (! is_numeric($reportId)) {
            return;
        }

        $formulaIds = DB::table('formulas')
            ->whereIn('code', [
                KpiCriterionSpecification::Competition,
                KpiCriterionSpecification::Maximum,
                KpiCriterionSpecification::Unlimited,
            ])
            ->pluck('id', 'code');

        foreach (KpiCriterionSpecification::criteria() as $code => $rule) {
            $criterion = DB::table('criteria')
                ->where('report_id', $reportId)
                ->where('code', $code)
                ->first(['id', 'desc']);

            if ($criterion === null || ! $formulaIds->has($rule['formula'])) {
                continue;
            }

            $attributes = [
                'formula_id' => $formulaIds->get($rule['formula']),
                'file_limit' => $rule['file_limit'],
                'observation' => $rule['observation'],
                'ai_submission_max_point' => $rule['ai_submission_max_point'] ?? null,
                'divide_ai_point_by_authors' => $rule['divide_ai_point_by_authors'] ?? null,
                'updated_at' => now(),
            ];

            if (isset($rule['description_uz'])) {
                $description = json_decode($criterion->desc ?? '[]', true);
                $description = is_array($description) ? $description : [];
                $description['uz'] = $rule['description_uz'];
                $attributes['desc'] = json_encode($description, JSON_UNESCAPED_UNICODE);
            }

            DB::table('criteria')->where('id', $criterion->id)->update($attributes);

            foreach ($rule['scores'] as $evaluation => $score) {
                DB::table('criterion_evaluations')->updateOrInsert(
                    [
                        'criterion_id' => $criterion->id,
                        'evaluation' => $evaluation,
                    ],
                    [
                        'has' => $score === null ? '0' : '1',
                        'score' => $score ?? 0,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
            }
        }

        $doctoralCriterionIds = DB::table('criteria')
            ->where('report_id', $reportId)
            ->whereIn('code', ['3.1.6', '3.1.7'])
            ->pluck('id');

        DB::table('criterion_manual_score_options')
            ->whereIn('criterion_id', $doctoralCriterionIds)
            ->whereIn('code', ['dsc_diploma', 'phd_diploma'])
            ->update(['point' => 3, 'updated_at' => now()]);
    }
};
