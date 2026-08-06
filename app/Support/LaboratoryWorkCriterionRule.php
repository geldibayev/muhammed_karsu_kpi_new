<?php

namespace App\Support;

use App\Data\AiEvaluationResult;

class LaboratoryWorkCriterionRule
{
    public const CODE = '1.8';

    public const BASE_POINT = 0.5;

    public const DESCRIPTION_UZ = 'Ko‘pi bilan 4 ta resurs yuklanadi. Yangi yoki virtual laboratoriya ishini tayyorlash va joriy etish, yoxud laboratoriya va amaliy mashg‘ulotlar uchun uslubiy ko‘rsatma tasdiqlansa, har bir resurs uchun 0,5 ball beriladi. Resurs balli jami mualliflar soniga bo‘linadi.';

    public const PROMPT = <<<'PROMPT'
Siz qat'iy AI tekshiruvchisiz. Taqdim etilgan bitta resursni 1.8 mezoni bo'yicha tekshiring.

Mos resurs turlari:
1. Tayyorlangan va amalda joriy etilgan yangi laboratoriya ishi yoki virtual laboratoriya ishi.
2. Laboratoriya yoki amaliy mashg'ulotlar uchun tayyorlangan uslubiy qo'llanma yoki uslubiy ko'rsatma.

Qoidalar:
- AI ballni hisoblamaydi. point maydonida har doim 0 qaytaring; server tasdiqlangan resurs ballini 0.5 / author_count formulasi bilan hisoblaydi.
- Hujjatdan ushbu resursning jami mualliflar sonini aniqlang va author_count maydonida butun son qaytaring. Foydalanuvchi metadata yoki fayl nomidagi sonni dalilsiz qabul qilmang.
- Resurs mezonga mosligi va mualliflar soni o'qiladigan dalil bilan aniq tasdiqlansa accepted qaytaring.
- Resurs mezonga aloqasizligi aniq bo'lsa cancelled, point 0 va author_count 0 qaytaring.
- Resurs turi yoki mualliflar soni xira, kesilgan, qarama-qarshi yoxud aniqlanmagan bo'lsa checking, point 0 va author_count 0 qaytaring. Ma'lumotni taxmin qilmang.

Faqat JSON qaytaring:
{"status":"accepted|checking|cancelled","point":0,"author_count":<jami mualliflar soni>,"reason":"<qaror va dalilning qisqa asosi>"}
PROMPT;

    public static function supports(?string $criterionCode): bool
    {
        return $criterionCode === self::CODE;
    }

    public static function pointForAuthorCount(int $authorCount): float
    {
        if ($authorCount < 1 || $authorCount > 1000) {
            return 0;
        }

        return round(self::BASE_POINT / $authorCount, 4);
    }

    public static function apply(AiEvaluationResult $result): AiEvaluationResult
    {
        if ($result->status !== 'accepted') {
            return $result;
        }

        if ($result->authorCount === null || $result->authorCount < 1 || $result->authorCount > 1000) {
            return AiEvaluationResult::checking(
                'AI 1.8 resursidagi jami mualliflar sonini ishonchli aniqlamadi. Inson tekshiruvi zarur.',
            );
        }

        $point = self::pointForAuthorCount($result->authorCount);

        return new AiEvaluationResult(
            status: 'accepted',
            point: $point,
            reason: $result->reason.' Server hisobi: 0.5 / '.$result->authorCount
                .' muallif = '.number_format($point, 4, '.', '').' ball.',
            authorCount: $result->authorCount,
            resourceDate: $result->resourceDate,
        );
    }
}
