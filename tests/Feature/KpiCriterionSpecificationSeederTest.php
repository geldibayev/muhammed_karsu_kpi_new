<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\CriterionManualScoreOption;
use App\Models\Formula;
use App\Models\Report;
use App\Support\FixedPerResourceHumanReviewCriterionRule;
use App\Support\IndustryFundingCriterionRule;
use App\Support\KpiCriterionSpecification;
use App\Support\MasterClassCriterionRule;
use App\Support\OakArticleCriterionRule;
use App\Support\ProfessionalDevelopmentCriterionRule;
use Database\Seeders\CriterionSeeder;
use Database\Seeders\KpiCriterionSpecificationSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\OptionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class KpiCriterionSpecificationSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reference_and_criterion_seeders_can_be_run_twice_without_duplicates(): void
    {
        foreach ([OptionSeeder::class, LanguageSeeder::class, CriterionSeeder::class, KpiCriterionSpecificationSeeder::class] as $seeder) {
            $this->seed($seeder);
        }

        foreach ([OptionSeeder::class, LanguageSeeder::class, CriterionSeeder::class, KpiCriterionSpecificationSeeder::class] as $seeder) {
            $this->seed($seeder);
        }

        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseCount('formulas', 3);
        $this->assertDatabaseCount('criteria', 39);
        $this->assertDatabaseCount('criterion_evaluations', 140);
        $this->assertDatabaseCount('criterion_years', 35);
        $this->assertSame(34, Criterion::query()->where('ai_model', 'gemini-3.5-flash-lite')->count());
        $this->assertSame(5, Criterion::query()->where('ai_model', 'gemini-3.5-flash')->count());
        $this->assertFalse(
            Criterion::query()
                ->whereIn('ai_model', ['gemini-2.5-flash', 'gemini-2.5-pro'])
                ->exists(),
        );

        $criterion = Criterion::query()->where('code', '1.4')->firstOrFail();

        $this->assertSame(
            'Darsliklik va o‘quv qo‘llanmalarni boshqa tillardan tarjima qilganligi',
            $criterion->name['uz'],
        );
        $this->assertSame(
            'Creating electronic textbooks and teaching aids or translating them into other languages',
            $criterion->name['en'],
        );
        $this->assertSame(
            'Belgilangan tartib va talablar asosida darslik va o‘quv qo‘llanmalarni boshqa tillardan tarjima qilinganligi tayyorlanib chop qilinganligi hamda ushbu o‘quv adabiyoti bo‘yicha universitetning nashr ruxsatnomasi, ISBN raqami asosida aniqlanadi. Mualliflik ulushi inobatga olinadi.',
            $criterion->desc['uz'],
        );
        $this->assertSame(
            'Based on the established procedure and requirements, the creation of electronic textbooks and teaching aids or their translation into other languages, as well as the preparation and publication of this educational literature, is determined on the basis of the university’s publication permit and ISBN number. The author’s contribution is taken into account.',
            $criterion->desc['en'],
        );
    }

    public function test_new_criteria_use_the_cost_efficient_default_ai_model(): void
    {
        $report = Report::query()->create([
            'code' => 'default-ai-model-test',
            'name' => ['uz' => 'Standart AI modeli testi'],
            'status' => '1',
        ]);

        $criterion = Criterion::query()->create([
            'name' => ['uz' => 'AI mezoni'],
            'report_id' => $report->getKey(),
            'upload' => '0',
            'status' => '1',
        ]);

        $this->assertSame('gemini-3.5-flash-lite', $criterion->refresh()->ai_model);
    }

    public function test_it_applies_the_complete_pdf_matrix_idempotently(): void
    {
        $this->seed(OptionSeeder::class);

        $report = Report::query()->create([
            'code' => 'audit-test',
            'name' => ['uz' => 'Audit testi'],
            'status' => '1',
        ]);
        $parents = collect(range(1, 4))->mapWithKeys(function (int $section) use ($report): array {
            $criterion = Criterion::query()->create([
                'code' => (string) $section,
                'name' => ['uz' => "{$section}-bo‘lim"],
                'report_id' => $report->getKey(),
                'sort_order' => $section,
                'status' => '1',
            ]);

            return [(string) $section => $criterion];
        });
        $defaultFormula = Formula::query()->where('code', Formula::Competition)->firstOrFail();

        foreach (KpiCriterionSpecification::currentCriteria() as $code => $rule) {
            Criterion::query()->create([
                'code' => $code,
                'name' => ['uz' => "{$code} mezoni"],
                'parent_id' => $parents->get(strtok($code, '.'))->getKey(),
                'report_id' => $report->getKey(),
                'formula_id' => $defaultFormula->getKey(),
                'status' => '1',
            ]);
        }

        foreach (['3.1.6' => 'dsc_diploma', '3.1.7' => 'phd_diploma'] as $code => $optionCode) {
            CriterionManualScoreOption::query()->create([
                'criterion_id' => Criterion::query()->where('code', $code)->value('id'),
                'code' => $optionCode,
                'label' => ['uz' => 'Diplom'],
                'point' => 1,
                'active' => true,
            ]);
        }

        $this->seed(KpiCriterionSpecificationSeeder::class);
        $this->seed(KpiCriterionSpecificationSeeder::class);

        $this->assertDatabaseCount('criterion_evaluations', 140);
        foreach (KpiCriterionSpecification::currentCriteria() as $code => $rule) {
            $this->assertCriterion(
                $code,
                $rule['formula'],
                $rule['file_limit'],
                $rule['observation'],
                array_values($rule['scores']),
            );
        }

        foreach (['3.1.3', '3.1.11'] as $code) {
            $this->assertSame(10, Criterion::query()->where('code', $code)->value('file_limit'));
        }

        $hIndexCriterion = Criterion::query()->where('code', '3.1.4')->firstOrFail();
        $this->assertSame('site:profile:index', $hIndexCriterion->checking);
        $this->assertSame('0', $hIndexCriterion->upload);
        $this->assertSame(
            'Xirsh indeksi quyidagicha baholanadi: h=0 uchun 0 ball, h≤2 uchun 25%, h=3 uchun 50%, h=4 uchun 75%, h=5 uchun 100%. h=5 dan yuqori har bir qo‘shimcha birlik uchun 1 ball qo‘shiladi. Web of Science ko‘rsatkichi alohida baholanadi. Scopus va ResearchGate ko‘rsatkichlaridan esa faqat yuqori natija hisobga olinadi.',
            $hIndexCriterion->desc['uz'],
        );

        $criterionThreeOneFifteen = Criterion::query()->where('code', '3.1.15')->firstOrFail();
        $this->assertSame('ai', $criterionThreeOneFifteen->checking);
        $this->assertSame('1', $criterionThreeOneFifteen->upload);
        $this->assertDatabaseHas('criterion_evaluations', [
            'criterion_id' => $criterionThreeOneFifteen->getKey(),
            'evaluation' => 'hold_degrees',
            'has' => '1',
            'score' => 2,
        ]);
        foreach (['no_degrees', 'foreign_lang', 'physical'] as $evaluation) {
            $this->assertDatabaseHas('criterion_evaluations', [
                'criterion_id' => $criterionThreeOneFifteen->getKey(),
                'evaluation' => $evaluation,
                'has' => '0',
                'score' => 0,
            ]);
        }

        $criterionThreeOneThirteen = Criterion::query()->where('code', '3.1.13')->firstOrFail();
        $this->assertSame(
            IndustryFundingCriterionRule::PROMPT,
            $criterionThreeOneThirteen->ai_prompt,
        );

        $this->assertSame(
            OakArticleCriterionRule::PROMPT,
            Criterion::query()->where('code', OakArticleCriterionRule::CODE)->value('ai_prompt'),
        );
        $criterionThreeOneTwo = Criterion::query()
            ->where('code', Criterion::IMPACT_FACTOR_AI_HUMAN_REVIEW_CODE)
            ->firstOrFail();
        $this->assertSame(4, $criterionThreeOneTwo->file_limit);
        $this->assertTrue($criterionThreeOneTwo->divide_ai_point_by_authors);
        $this->assertSame(
            'Har bir tasdiqlangan resurs uchun ilmiy darajaga ega foydalanuvchiga 0,5 ball, ilmiy darajaga ega bo‘lmagan foydalanuvchiga 0,75 ball beriladi. Ball maqoladagi jami mualliflar soniga teng taqsimlanadi.',
            $criterionThreeOneTwo->desc['uz'],
        );

        $criterionTwoOneFive = Criterion::query()->where('code', '2.1.5')->firstOrFail();
        $this->assertSame(
            ProfessionalDevelopmentCriterionRule::PROMPT,
            $criterionTwoOneFive->ai_prompt,
        );

        $criterionTwoOneTwo = Criterion::query()
            ->with('criterionEvaluations')
            ->where('code', FixedPerResourceHumanReviewCriterionRule::TWO_ONE_TWO_CODE)
            ->firstOrFail();
        $this->assertSame(
            FixedPerResourceHumanReviewCriterionRule::twoOneTwoPrompt(),
            $criterionTwoOneTwo->ai_prompt,
        );
        $this->assertSame(2.0, $criterionTwoOneTwo->ai_submission_max_point);
        $this->assertSame('0', $criterionTwoOneTwo->criterionEvaluations
            ->firstWhere('evaluation', 'foreign_lang')?->has);

        $criterionThreeOneSeven = Criterion::query()->where('code', '3.1.7')->firstOrFail();
        $this->assertSame(
            FixedPerResourceHumanReviewCriterionRule::threeOneSevenPrompt(),
            $criterionThreeOneSeven->ai_prompt,
        );

        $criterionThreeOneEleven = Criterion::query()->where('code', '3.1.11')->firstOrFail();
        $this->assertSame(
            FixedPerResourceHumanReviewCriterionRule::threeOneElevenPrompt(),
            $criterionThreeOneEleven->ai_prompt,
        );

        $criterionThreeOneFourteen = Criterion::query()->where('code', '3.1.14')->firstOrFail();
        $this->assertSame(
            FixedPerResourceHumanReviewCriterionRule::threeOneFourteenPrompt(),
            $criterionThreeOneFourteen->ai_prompt,
        );

        $criterionOneTwo = Criterion::query()
            ->with('criterionEvaluations')
            ->where('code', '1.2')
            ->firstOrFail();
        $this->assertSame(6, $criterionOneTwo->criterionEvaluations
            ->firstWhere('evaluation', 'hold_degrees')?->score);
        $this->assertSame(5, $criterionOneTwo->criterionEvaluations
            ->firstWhere('evaluation', 'no_degrees')?->score);
        $this->assertSame(1, Criterion::query()->where('code', '1.4')->value('file_limit'));

        $masterClassCriterion = Criterion::query()
            ->with('criterionEvaluations')
            ->where('code', MasterClassCriterionRule::CURRENT_CODE)
            ->firstOrFail();
        $this->assertSame(1, $masterClassCriterion->file_limit);
        $this->assertSame(Formula::Maximum, $masterClassCriterion->formula->code);
        $this->assertSame(MasterClassCriterionRule::PROMPT, $masterClassCriterion->ai_prompt);
        foreach (['hold_degrees' => 2, 'no_degrees' => 2, 'foreign_lang' => 3, 'physical' => 4] as $evaluation => $score) {
            $this->assertSame(
                $score,
                $masterClassCriterion->criterionEvaluations->firstWhere('evaluation', $evaluation)?->score,
            );
        }

        $this->assertDatabaseHas('criterion_manual_score_options', ['code' => 'dsc_diploma', 'point' => 3]);
        $this->assertDatabaseHas('criterion_manual_score_options', ['code' => 'phd_diploma', 'point' => 3]);
    }

    /** @param array{0: int|null, 1: int|null, 2: int|null, 3: int|null} $scores */
    private function assertCriterion(
        string $code,
        string $formulaCode,
        int $fileLimit,
        string $observation,
        array $scores,
    ): void {
        $criterion = Criterion::query()
            ->with(['formula', 'criterionEvaluations'])
            ->where('code', $code)
            ->firstOrFail();

        $this->assertSame($formulaCode, $criterion->formula->code);
        $this->assertSame($fileLimit, $criterion->file_limit);
        $this->assertSame($observation, $criterion->observation);

        foreach (array_combine(['hold_degrees', 'no_degrees', 'foreign_lang', 'physical'], $scores) as $category => $score) {
            $evaluation = $criterion->criterionEvaluations->firstWhere('evaluation', $category);
            $this->assertNotNull($evaluation);
            $this->assertSame($score === null ? '0' : '1', $evaluation->has);
            $this->assertSame($score ?? 0, $evaluation->score);
        }
    }
}
