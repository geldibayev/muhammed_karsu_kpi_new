<?php

namespace App\Support;

use App\Data\AiEvaluationResult;

class FixedPerResourceHumanReviewCriterionRule
{
    /** @var array<string, array<string, float>> */
    private const POINTS = [
        '1.10' => [
            'hold_degrees' => 2.0,
            'no_degrees' => 2.0,
            'foreign_lang' => 3.0,
            'physical' => 4.0,
        ],
        '3.1.12' => [
            'hold_degrees' => 3.0,
            'no_degrees' => 3.0,
            'foreign_lang' => 3.0,
            'physical' => 3.0,
        ],
        '3.1.7' => [
            'hold_degrees' => 3.0,
            'foreign_lang' => 3.0,
        ],
        '3.1.14' => [
            'hold_degrees' => 4.0,
            'no_degrees' => 1.0,
            'foreign_lang' => 1.0,
            'physical' => 1.0,
        ],
        '4.1.3' => [
            'hold_degrees' => 0.5,
            'no_degrees' => 0.75,
            'foreign_lang' => 0.25,
            'physical' => 0.75,
        ],
        '4.1.4' => [
            'hold_degrees' => 0.5,
            'no_degrees' => 0.75,
            'foreign_lang' => 0.5,
            'physical' => 1.0,
        ],
        '4.1.5' => [
            'hold_degrees' => 1.0,
            'no_degrees' => 1.0,
            'foreign_lang' => 1.0,
            'physical' => 1.0,
        ],
    ];

    /** @return array<int, string> */
    public static function criterionCodes(): array
    {
        return array_keys(self::POINTS);
    }

    public static function supports(?string $criterionCode): bool
    {
        return $criterionCode !== null && array_key_exists($criterionCode, self::POINTS);
    }

    public static function pointFor(string $criterionCode, string $evaluationCategory): ?float
    {
        return self::POINTS[$criterionCode][$evaluationCategory] ?? null;
    }

    public static function normalizeAiResult(
        AiEvaluationResult $result,
        string $criterionCode,
        string $evaluationCategory,
    ): AiEvaluationResult {
        if (! self::supports($criterionCode)) {
            return $result;
        }

        $fixedPoint = self::pointFor($criterionCode, $evaluationCategory);

        if ($fixedPoint === null) {
            return AiEvaluationResult::checking(
                'Foydalanuvchi baholash toifasi uchun qat’iy ball sozlanmagan.',
            );
        }

        return new AiEvaluationResult(
            status: $result->status,
            point: $result->status === 'accepted' ? $fixedPoint : 0,
            reason: $result->reason,
            authorCount: $result->authorCount,
            resourceDate: $result->resourceDate,
            pageCount: $result->pageCount,
            receivedAmount: $result->receivedAmount,
        );
    }

    public static function threeOneTwelvePrompt(): string
    {
        return <<<'PROMPT'
Siz qat'iy AI baholovchisiz. Taqdim etilgan hujjatlarni (to'garak tashkil etish to'g'risidagi buyruq va to'garakning tasdiqlangan ish rejasi) tahlil qiling.
Baholash qoidalari jami %pointing% ballgacha:
1. Hujjatlar orasida professor-o'qituvchi nomiga rasmiylashtirilgan to'garak tashkil etish to'g'risidagi buyruq (yoki ruxsatnoma) bo'lishi shart.
2. Hujjatlar orasida to'garakning mavzular va muddatlar ko'rsatilgan tasdiqlangan ish rejasi bo'lishi shart.

Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
- Agar ham rasmiy buyruq, ham tasdiqlangan ish rejasi mavjud bo'lsa: "accepted" statusini bering va "point" qismiga 3 yozing.
- Agar hujjatlar xira bo'lsa, o'qib bo'lmasa, yoki hujjatlarning biri (buyruq yoki reja) yetishmayotgan bo'lsa (administrator ko'rib chiqishi uchun): "checking" statusini bering.
- Agar hujjatlarning ushbu mezonga umuman aloqasi bo'lmasa yoki soxta bo'lsa: "cancelled" statusini bering.

Javobni hech qanday markdown belgilarisiz (```json...``` kabi emas) va qo'shimcha so'zlarsiz, faqatgina quyidagi qat'iy JSON formatida qaytaring:
{"status": "accepted|checking|cancelled", "point": <raqam: 3 yoki 0>, "reason": "<Qabul qilingan qarorning sababi va hujjatlardagi holat haqida qisqacha izoh>"}
PROMPT;
    }

    public static function threeOneSevenPrompt(): string
    {
        return <<<'PROMPT'
Siz 3.1.7 mezoni bo‘yicha PhD ilmiy darajali kadr tayyorlanganini tekshiruvchi qat’iy AI yordamchisiz.

VAZIFA:
- OAK (VAK) tasdiqlagan PhD diplomi yoki unga teng rasmiy hujjat mavjudligini tekshiring.
- Hujjatda PhD darajasini olgan shaxs va ilmiy rahbar haqidagi ma’lumotlar aniq ko‘rsatilgan bo‘lishi kerak.
- Taqdim etgan professor-o‘qituvchi ushbu PhD kadrning ilmiy rahbari ekanligi tasdiqlanishi kerak.
- Ballni o‘zingiz tanlamang. Tasdiqlangan resurs uchun server foydalanuvchi toifasiga ko‘ra 3 ball beradi.

QAROR:
- Barcha talablar aniq tasdiqlansa accepted qaytaring.
- Hujjat xira, kesilgan yoki PhD darajasi yoxud ilmiy rahbar ma’lumoti noaniq bo‘lsa checking qaytaring.
- Hujjat mezonga aloqasizligi yoki taqdim etgan professor-o‘qituvchi ilmiy rahbar emasligi aniq bo‘lsa cancelled qaytaring.

Point maydoniga accepted holatida ham 0 yozing: yakuniy ballni server hisoblaydi. Reason ichida PhD kadr, diplom va ilmiy rahbarlikni tasdiqlovchi dalillarni qisqa yozing.
Javobni faqat quyidagi JSON formatida qaytaring:
{"status":"accepted|checking|cancelled","point":0,"reason":"qaror sababi"}
PROMPT;
    }

    public static function threeOneFourteenPrompt(): string
    {
        return <<<'PROMPT'
Siz 3.1.14 davlat grantlari asosidagi ilmiy-tadqiqot loyihalarini tekshiruvchi qat'iy AI yordamchisiz.

VAZIFA:
- Loyiha aynan universitet tomonidan bajarilayotganini rasmiy hujjat orqali tekshiring. Boshqa OTM tomonidan bajarilayotgan loyiha hisobga olinmaydi.
- Professor-o'qituvchining shu loyihada rahbar, a'zo yoki ijrochi sifatida ishtiroki aniq tasdiqlanishi shart.
- Ballni o'zingiz tanlamang. Tasdiqlangan bitta resurs uchun server foydalanuvchi toifasiga qarab ilmiy darajalilarga 4 ball, qolgan toifalarga 1 ball beradi.

QAROR:
- Loyiha universitet tomonidan bajarilayotgani va professor-o'qituvchining ishtiroki aniq tasdiqlansa accepted qaytaring.
- Hujjat xira, kesilgan, universitet nomi yoki ishtirok noaniq bo'lsa checking qaytaring.
- Loyiha boshqa OTM tomonidan bajarilayotgani, professor-o'qituvchi unda ishtirok etmagani yoki hujjat mezonga aloqasizligi aniq bo'lsa cancelled qaytaring.

Point maydoniga accepted holatida ham 0 yozing: yakuniy 4 yoki 1 ballni server ishonchli foydalanuvchi toifasi asosida hisoblaydi. Reason ichida loyiha nomi, uni universitet bajarayotganining dalili va professor-o'qituvchining ishtirokini qisqa yozing.
PROMPT;
    }
}
