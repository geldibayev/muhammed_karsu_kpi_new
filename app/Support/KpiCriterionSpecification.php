<?php

namespace App\Support;

class KpiCriterionSpecification
{
    public const RetiredCodes = ['1.5', '1.6'];

    public const Competition = 'competition';

    public const Maximum = 'maximum';

    public const Unlimited = 'unlimited';

    /**
     * Canonical machine-readable values from storage/app/private/kriteriya.pdf.
     * A null category score means that the criterion does not apply to that category.
     *
     * @return array<string, array{
     *     formula: string,
     *     file_limit: int,
     *     observation: string,
     *     scores: array{hold_degrees: int|null, no_degrees: int|null, foreign_lang: int|null, physical: int|null},
     *     ai_submission_max_point?: float,
     *     divide_ai_point_by_authors?: bool,
     *     description_uz?: string,
     *     ai_prompt?: string
     * }>
     */
    public static function criteria(): array
    {
        return [
            '1.1' => self::rule(
                self::Maximum,
                3,
                'current',
                [3, 6, 4, 4],
                descriptionUz: 'Ko‘pi bilan 3 ta o‘quv kontenti YouTube havolasi orqali taqdim etiladi. Tasdiqlangan videodars maksimal ballning 50%, videorolik 40%, taqdimot 10% miqdorida baholanadi. Maksimal ball: chet tillari va jismoniy madaniyat yo‘nalishlari uchun 4 ball, ilmiy darajali uchun 3 ball, ilmiy darajasiz uchun 6 ball.',
            ),
            '1.2' => self::rule(self::Unlimited, 0, 'current', [6, 5, 5, 5], 100, true),
            '1.3' => self::rule(self::Unlimited, 0, 'current', [5, 4, 4, 4], 100, true),
            '1.4' => self::rule(self::Maximum, 1, 'current', [5, 4, 4, 4], null, true),
            '1.5' => self::rule(self::Competition, 0, 'current', [3, 3, 3, 3]),
            '1.6' => self::rule(self::Competition, 0, 'current', [4, 4, 4, 4]),
            '1.7' => self::rule(self::Maximum, 0, 'current', [10, 10, 10, 10]),
            '1.8' => self::rule(
                self::Maximum,
                4,
                'current',
                [2, 2, null, null],
                LaboratoryWorkCriterionRule::BASE_POINT,
                false,
                LaboratoryWorkCriterionRule::DESCRIPTION_UZ,
                LaboratoryWorkCriterionRule::PROMPT,
            ),
            '1.9' => self::rule(
                self::Competition,
                0,
                'previous',
                [3, 3, 3, 3],
                descriptionUz: 'Abituriyentlarni universitetga jalb qilish, xorijiy talabalarni jalb qilish va bitiruvchilarning bandligini ta’minlash bo‘yicha har bir tasdiqlangan yo‘nalish uchun 1 balldan beriladi. Avvalgi o‘quv yili natijalari hisobga olinadi.',
            ),
            '1.10' => self::rule(
                self::Maximum,
                1,
                'current',
                [2, 2, 3, 4],
                aiPrompt: MasterClassCriterionRule::PROMPT,
            ),

            '2.1.1' => self::rule(self::Maximum, 1, 'current', [1, 2, 2, 2]),
            '2.1.2' => self::rule(
                self::Maximum,
                1,
                'current',
                [2, 2, null, 2],
                2,
                false,
                aiPrompt: FixedPerResourceHumanReviewCriterionRule::twoOneTwoPrompt(),
            ),
            '2.1.3' => self::rule(
                self::Maximum,
                1,
                'certificate_expire',
                [10, 10, 10, 10],
                descriptionUz: ForeignLanguageCertificateCriterionRule::DESCRIPTION_UZ,
            ),
            '2.1.4' => self::rule(self::Maximum, 0, 'current', [4, 4, 4, 4]),
            '2.1.5' => self::rule(
                self::Maximum,
                1,
                'last3years',
                [2, 3, 2, 3],
                divideAiPointByAuthors: false,
                descriptionUz: ProfessionalDevelopmentCriterionRule::DESCRIPTION_UZ,
                aiPrompt: ProfessionalDevelopmentCriterionRule::PROMPT,
            ),
            '2.1.6' => self::rule(self::Maximum, 1, 'current', [3, 3, 4, 4]),

            '3.1.1' => self::rule(
                self::Maximum,
                4,
                'current',
                [2, 3, 3, 3],
                divideAiPointByAuthors: true,
                descriptionUz: OakArticleCriterionRule::DESCRIPTION_UZ,
                aiPrompt: OakArticleCriterionRule::PROMPT,
            ),
            '3.1.2' => self::rule(
                self::Maximum,
                4,
                'current',
                [2, 3, 3, 3],
                divideAiPointByAuthors: true,
                descriptionUz: 'Har bir tasdiqlangan resurs uchun ilmiy darajaga ega foydalanuvchiga 0,5 ball, ilmiy darajaga ega bo‘lmagan foydalanuvchiga 0,75 ball beriladi. Ball maqoladagi jami mualliflar soniga teng taqsimlanadi.',
            ),
            '3.1.3' => self::rule(
                self::Unlimited,
                10,
                'current',
                [20, 20, 20, 20],
                ScopusCriterionRule::MAXIMUM_POINT,
                false,
                ScopusCriterionRule::DESCRIPTION_UZ,
                ScopusCriterionRule::PROMPT,
            ),
            '3.1.4' => self::rule(
                self::Maximum,
                0,
                'current_state',
                [3, 2, 2, 2],
                descriptionUz: HIndexCriterionRule::DESCRIPTION_UZ,
            ),
            '3.1.5' => self::rule(self::Maximum, 1, 'current', [2, 3, 3, 3]),
            '3.1.6' => self::rule(self::Unlimited, 0, 'current', [3, null, null, null]),
            '3.1.7' => self::rule(
                self::Unlimited,
                0,
                'current',
                [3, null, 3, null],
                aiPrompt: FixedPerResourceHumanReviewCriterionRule::threeOneSevenPrompt(),
            ),
            '3.1.8' => self::rule(
                self::Unlimited,
                4,
                'current',
                [3, 4, 4, 4],
                4,
                false,
                PatentCriterionRule::DESCRIPTION_UZ,
                PatentCriterionRule::PROMPT,
            ),
            '3.1.9' => self::rule(self::Competition, 2, 'current', [1, 2, 2, 2]),
            '3.1.10' => self::rule(
                self::Maximum,
                1,
                'current',
                [2, 4, 2, 2],
                4,
                false,
                aiPrompt: FixedPerResourceHumanReviewCriterionRule::threeOneTenPrompt(),
            ),
            '3.1.11' => self::rule(
                self::Unlimited,
                10,
                'current',
                [3, 4, 4, 4],
                4,
                false,
                aiPrompt: FixedPerResourceHumanReviewCriterionRule::threeOneElevenPrompt(),
            ),
            '3.1.12' => self::rule(
                self::Maximum,
                1,
                'current',
                [3, 3, 3, 3],
                aiPrompt: FixedPerResourceHumanReviewCriterionRule::threeOneTwelvePrompt(),
            ),
            '3.1.13' => self::rule(
                self::Unlimited,
                0,
                'current',
                [5, 4, 4, 4],
                999999.99,
                true,
                'Xo‘jalik shartnomasi asosida jalb qilingan har 1 million so‘m uchun 1 ball beriladi. Umumiy ball hammualliflar soniga bo‘linadi.',
                IndustryFundingCriterionRule::PROMPT,
            ),
            '3.1.14' => self::rule(
                self::Maximum,
                1,
                'project_finished',
                [4, 1, 1, 1],
                aiPrompt: FixedPerResourceHumanReviewCriterionRule::threeOneFourteenPrompt(),
            ),
            '3.1.15' => self::rule(self::Maximum, 1, 'end_of_council', [2, null, null, null]),

            '4.1.1' => self::rule(
                self::Maximum,
                4,
                'current',
                [3, 3, 2, 3],
                0.75,
                false,
                aiPrompt: FixedPerResourceHumanReviewCriterionRule::fourOneOnePrompt(),
            ),
            '4.1.2' => self::rule(
                self::Maximum,
                1,
                'current',
                [1, 1, 1, 2],
                2,
                false,
                aiPrompt: FixedPerResourceHumanReviewCriterionRule::fourOneTwoPrompt(),
            ),
            '4.1.3' => self::rule(self::Maximum, 4, 'current', [2, 3, 1, 3]),
            '4.1.4' => self::rule(self::Maximum, 4, 'current', [2, 3, 2, 4]),
            '4.1.5' => self::rule(self::Maximum, 2, 'current', [2, 1, 2, 2]),
            '4.1.6' => self::rule(self::Maximum, 0, 'current', [2, 2, 2, 2]),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function currentCriteria(): array
    {
        $criteria = [];

        foreach (self::criteria() as $code => $rule) {
            $currentCode = match ($code) {
                '1.5', '1.6' => null,
                '1.7' => '1.5',
                '1.8' => '1.6',
                '1.9' => '1.7',
                '1.10' => '1.8',
                default => $code,
            };

            if ($currentCode !== null) {
                $criteria[$currentCode] = $rule;
            }
        }

        return $criteria;
    }

    /**
     * @param  array{0: int|null, 1: int|null, 2: int|null, 3: int|null}  $scores
     * @return array<string, mixed>
     */
    private static function rule(
        string $formula,
        int $fileLimit,
        string $observation,
        array $scores,
        ?float $aiSubmissionMaxPoint = null,
        ?bool $divideAiPointByAuthors = null,
        ?string $descriptionUz = null,
        ?string $aiPrompt = null,
    ): array {
        $rule = [
            'formula' => $formula,
            'file_limit' => $fileLimit,
            'observation' => $observation,
            'scores' => [
                'hold_degrees' => $scores[0],
                'no_degrees' => $scores[1],
                'foreign_lang' => $scores[2],
                'physical' => $scores[3],
            ],
        ];

        if ($aiSubmissionMaxPoint !== null) {
            $rule['ai_submission_max_point'] = $aiSubmissionMaxPoint;
        }

        if ($divideAiPointByAuthors !== null) {
            $rule['divide_ai_point_by_authors'] = $divideAiPointByAuthors;
        }

        if ($descriptionUz !== null) {
            $rule['description_uz'] = $descriptionUz;
        }

        if ($aiPrompt !== null) {
            $rule['ai_prompt'] = $aiPrompt;
        }

        return $rule;
    }
}
