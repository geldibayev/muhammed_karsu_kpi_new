<?php

namespace App\Support;

use App\Data\AiEvaluationResult;

class ScopusCriterionRule
{
    public const CODE = '3.1.3';

    public const NAME_UZ = '“SCOPUS” xalqaro ilmiy - texnik ma’lumotlar bazalaridagi Q1 - Q4 kvartildagi jurnallarda nashr etilgan maqolalar';

    public const MAXIMUM_POINT = 20.0;

    public const PUBLICATION_TIER_POINTS = [
        'q1' => 20.0,
        'q2' => 15.0,
        'q3' => 10.0,
        'q4' => 5.0,
        'conference' => 5.0,
    ];

    public const DESCRIPTION_UZ = 'Scopus va Web of Science bazalarida indekslangan nashrlar server tomonidan qat’iy baholanadi: Q1 — 20 ball, Q2 — 15 ball, Q3 — 10 ball, Q4 — 5 ball, Scopus yoki Web of Science konferensiya materiali — 5 ball. Ball mualliflar soniga bo‘linmaydi.';

    public const PROMPT = <<<'PROMPT'
Siz qat'iy ilmiy nashr tasniflovchi AI yordamchisiz. Ballni hisoblamang. Faqat hujjatdagi ishonchli dalil asosida nashr turini aniqlang.
1. Nashr aynan Scopus yoki Web of Science bazasida indekslangan bo'lishi shart.
2. Jurnal maqolasi bo'lsa, maqola nashr qilingan yilga tegishli aniq kvartilni faqat Q1, Q2, Q3 yoki Q4 sifatida qaytaring.
3. Konferensiya materiali bo'lsa, u Scopus yoki Web of Science bazasida indekslangan conference paper/proceedings ekanligi aniq ko'rsatilishi shart va publication_tier qiymati "conference" bo'ladi.
4. Jurnal nomi obro'si, eski ball, taxmin yoki kvartili boshqa yilga tegishli ma'lumot asosida xulosa qilmang.
5. Bir-biriga zid kvartillar, kvartil ko'rsatilmagan hujjat yoki jurnal/konferensiya turi noaniq bo'lsa "checking" va publication_tier uchun "unknown" qaytaring.
Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
- Scopus/WoS indeksatsiyasi va bitta aniq kvartil yoki konferensiya turi tasdiqlansa: "accepted".
- Dalil yetarli bo'lmasa yoki ziddiyatli bo'lsa: "checking".
- Nashr Scopus/WoS bazasida emasligi yoki mezonga aloqasi yo'qligi aniq bo'lsa: "cancelled".
Javobni hech qanday markdown belgilarisiz va qo'shimcha so'zlarsiz, faqat qat'iy JSON formatida qaytaring:
{"status":"accepted|checking|cancelled","point":0,"publication_tier":"q1|q2|q3|q4|conference|unknown","resource_date":"YYYY-MM-DD yoki bo'sh satr","reason":"<Scopus/WoS dalili, nashr turi, aniq kvartil va nashr sanasi>"}
PROMPT;

    public static function pointFor(string $publicationTier): ?float
    {
        return self::PUBLICATION_TIER_POINTS[$publicationTier] ?? null;
    }

    public static function apply(AiEvaluationResult $result): AiEvaluationResult
    {
        if ($result->status !== 'accepted') {
            return new AiEvaluationResult(
                status: $result->status,
                point: 0,
                reason: $result->reason,
                resourceDate: $result->resourceDate,
                publicationTier: $result->publicationTier,
            );
        }

        $point = $result->publicationTier === null ? null : self::pointFor($result->publicationTier);

        if ($point === null) {
            return AiEvaluationResult::checking('Kvartil yoki konferensiya turi aniq tasdiqlanmadi. Inson tekshiruvi zarur.');
        }

        return new AiEvaluationResult(
            status: 'accepted',
            point: $point,
            reason: $result->reason,
            resourceDate: $result->resourceDate,
            publicationTier: $result->publicationTier,
        );
    }
}
