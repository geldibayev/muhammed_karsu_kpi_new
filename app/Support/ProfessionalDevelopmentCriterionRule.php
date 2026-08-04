<?php

namespace App\Support;

use App\Data\AiEvaluationResult;

class ProfessionalDevelopmentCriterionRule
{
    public const CODE = '2.1.5';

    public const UNIVERSITY_TIERS = [
        'top_100',
        'top_300',
        'top_500',
        'top_1000',
    ];

    public const PROMPT = <<<'PROMPT'
Siz 2.1.5 mezoni bo‘yicha xorijda qayta tayyorlash, malaka oshirish, stajirovka yoki almashinuv dasturini tekshiruvchi qat’iy AI yordamchisiz.

TASDIQLASH TALABLARI:
- Sertifikat, ma’lumotnoma, xizmat safari buyrug‘i, diplom yoki boshqa rasmiy hujjat professor-o‘qituvchining xorijdagi ta’lim muassasasida onlayn yoki offlayn qatnashganini aniq tasdiqlashi kerak.
- Muassasa QS, THE yoki ARWU reytingidagi o‘rnini faqat o‘qiladigan ishonchli dalildan aniqlang. Reyting o‘rnini taxmin qilmang va o‘ylab topmang.
- Aniq reyting o‘rniga ko‘ra university_tier qiymatini tanlang:
  - 1–100 (100 ham kiradi): top_100;
  - 101–300 (300 ham kiradi): top_300;
  - 301–500 (500 ham kiradi): top_500;
  - 501–1000 (1000 ham kiradi): top_1000.

BALL QOIDASI:
- Ballni o‘zingiz hisoblamang va point maydoniga 0 yozing.
- Server top_100 uchun maksimal ballning 100 foizini, top_300 uchun 75 foizini, top_500 uchun 50 foizini, top_1000 uchun 25 foizini beradi.
- Foydalanuvchi toifasiga mos maksimal ball tizim tomonidan ishonchli aniqlanadi: ilmiy darajali va xorijiy til toifasi uchun 2 ball; ilmiy darajasiz va jismoniy toifa uchun 3 ball.

QAROR:
- Ishtirok va Top-1000 ichidagi reyting oralig‘i aniq tasdiqlansa accepted qaytaring.
- Hujjat xira, ishtirok yoki reyting oralig‘i noaniq bo‘lsa checking va university_tier uchun unknown qaytaring.
- Ishtirok mezonga mos emasligi yoki muassasa 1000 dan past o‘rinda ekanligi aniq bo‘lsa cancelled qaytaring; 1000 dan past holatda university_tier uchun outside_top_1000 yozing.

Javobda point har doim 0 bo‘lsin. Reason ichida hujjat turi, muassasa, tasdiqlangan faoliyat, reyting tizimi va aniq o‘rin/oraliqni yozing.
PROMPT;

    public const DESCRIPTION_UZ = 'QS, THE yoki ARWU reytingidagi xorijiy ta’lim muassasasida qayta tayyorlash, malaka oshirish, stajirovka yoki almashinuv dasturida qatnashganlik baholanadi. Top-100 (1–100) — maksimal ballning 100 foizi; Top-101–300 — 75 foizi; Top-301–500 — 50 foizi; Top-501–1000 — 25 foizi. 1000 dan past o‘rin uchun ball berilmaydi.';

    public static function supports(?string $criterionCode): bool
    {
        return $criterionCode === self::CODE;
    }

    public static function tierForRank(int $rank): ?string
    {
        return match (true) {
            $rank >= 1 && $rank <= 100 => 'top_100',
            $rank >= 101 && $rank <= 300 => 'top_300',
            $rank >= 301 && $rank <= 500 => 'top_500',
            $rank >= 501 && $rank <= 1000 => 'top_1000',
            default => null,
        };
    }

    public static function pointForUniversityTier(float $maximumPoint, string $universityTier): ?float
    {
        if (! in_array($maximumPoint, [2.0, 3.0], true)) {
            return null;
        }

        $percentage = match ($universityTier) {
            'top_100' => 1.0,
            'top_300' => 0.75,
            'top_500' => 0.5,
            'top_1000' => 0.25,
            default => null,
        };

        return $percentage === null ? null : round($maximumPoint * $percentage, 4);
    }

    public static function apply(AiEvaluationResult $result, float $maximumPoint): AiEvaluationResult
    {
        if ($result->status !== 'accepted') {
            return $result;
        }

        $point = $result->universityTier === null
            ? null
            : self::pointForUniversityTier($maximumPoint, $result->universityTier);

        if ($point === null) {
            return AiEvaluationResult::checking(
                'AI 2.1.5 mezoni uchun ruxsat etilgan universitet Top oralig‘ini qaytarmadi. Inson tekshiruvi zarur.',
            );
        }

        return new AiEvaluationResult(
            status: 'accepted',
            point: $point,
            reason: $result->reason,
            authorCount: $result->authorCount,
            resourceDate: $result->resourceDate,
            pageCount: $result->pageCount,
            receivedAmount: $result->receivedAmount,
            universityTier: $result->universityTier,
        );
    }
}
