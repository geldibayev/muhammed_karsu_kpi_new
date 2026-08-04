<?php

namespace App\Support;

class TranslatedEducationalLiteratureCriterionRule
{
    public const CODE = '1.4';

    /** @var array<int, string> */
    public const PREVIOUSLY_SCORED_CODES = ['1.2', '1.3'];

    public static function supports(?string $criterionCode): bool
    {
        return $criterionCode === self::CODE;
    }

    public static function aiInstruction(): string
    {
        return <<<'PROMPT'
TARJIMA HOLATINI MAJBURIY TEKSHIRISH:
- Bu mezon faqat avval boshqa tilda yaratilgan darslik yoki o‘quv qo‘llanmaning tarjimasi uchun. Oddiy darslik, o‘quv qo‘llanma yoki yangi yaratilgan asar tarjima hisoblanmaydi.
- Hujjatdan asl asarning manba tilini va tarjima qilingan nashrning maqsad tilini alohida aniqlang. Ular bir xil bo‘lishi mumkin emas.
- Tarjima ekanini titul varaq, bibliografik ma’lumot, nashr ruxsatnomasi yoki boshqa o‘qiladigan rasmiy dalil aniq tasdiqlashi shart. Faqat foydalanuvchi yozgan metadata yoki fayl nomiga ishonmang.
- Tarjima emasligi aniq bo‘lsa, is_translation false, cancelled statusi va 0 ball qaytaring. Tarjima holati yoki tillardan biri o‘qilmasa, checking statusi qaytaring.
- accepted faqat is_translation true bo‘lsa hamda source_language va target_language aniq, bo‘sh bo‘lmagan va o‘zaro farqli bo‘lsa mumkin.
PROMPT;
    }
}
