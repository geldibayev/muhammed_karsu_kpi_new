<?php

namespace Database\Seeders;

use App\Models\Criterion;
use App\Models\CriterionManualScoreOption;
use App\Models\CriterionReviewerAssignment;
use App\Models\Formula;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OavCriterionRuleSeeder extends Seeder
{
    private const CRITERION_CODE = '4/36';

    private const CRITERION_NAME = 'OAV yoki ijtimoiy tarmoqlarda universitet va mamlakatda amalga oshirilayotgan islohotlar yuzasidan chiqishlar qilganlig';

    private const FORMULA_NAME = 'Maksimal ballga asoslangan';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $criterion = $this->criterion();
            $formula = Formula::query()
                ->get(['id', 'name'])
                ->first(
                    fn (Formula $formula): bool => data_get($formula->name, 'uz') === self::FORMULA_NAME,
                );

            if ($formula === null) {
                throw new RuntimeException('Maksimal ballga asoslangan formula topilmadi.');
            }

            $criterion->update([
                'checking' => 'manual',
                'file_limit' => 4,
                'formula_id' => $formula->getKey(),
            ]);

            $criterion->criterionEvaluations()
                ->where('has', '1')
                ->update(['score' => 3]);

            CriterionManualScoreOption::query()
                ->where('criterion_id', $criterion->getKey())
                ->where('code', '!=', 'approved_resource')
                ->update(['active' => false]);

            CriterionManualScoreOption::query()->updateOrCreate(
                [
                    'criterion_id' => $criterion->getKey(),
                    'code' => 'approved_resource',
                ],
                [
                    'label' => ['uz' => 'Tasdiqlangan resurs'],
                    'point' => 0.75,
                    'sort_order' => 1,
                    'active' => true,
                ],
            );
        }, 3);
    }

    private function criterion(): Criterion
    {
        $criterionId = CriterionReviewerAssignment::query()
            ->where('criterion_code', self::CRITERION_CODE)
            ->value('criterion_id');

        $criterion = is_numeric($criterionId)
            ? Criterion::query()->find((int) $criterionId)
            : null;

        $criterion ??= Criterion::query()
            ->whereNotNull('parent_id')
            ->get(['id', 'name'])
            ->first(
                fn (Criterion $criterion): bool => data_get($criterion->name, 'uz') === self::CRITERION_NAME,
            );

        if ($criterion === null) {
            throw new RuntimeException('4/36 OAV kriteriyasi topilmadi.');
        }

        return $criterion;
    }
}
