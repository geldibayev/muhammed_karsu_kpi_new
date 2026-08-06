<?php

namespace App\Support;

class TranslatedEducationalLiteratureCriterionRule
{
    public const CODE = '1.4';

    public const PROMPT = <<<'PROMPT'
Siz qat'iy AI baholovchisiz. Taqdim etilgan hujjatni darslik yoki o'quv qo'llanmaning boshqa tildan qilingan tarjimasi sifatida tekshiring.

Majburiy talablar:
1. Hujjat oddiy kitob, yangi yozilgan darslik yoki yangi yozilgan o'quv qo'llanma emas, avval boshqa tilda mavjud bo'lgan darslik yoki o'quv qo'llanmaning tarjimasi bo'lishi shart.
2. Tarjima nashrining maqsad tili faqat o'zbek (uz), qoraqalpoq (kaa) yoki rus (ru) tillaridan biri bo'lishi mumkin.
3. ISBN raqami hujjatda aniq ko'rinishi shart.
4. Nashr yili tizim bergan ruxsat etilgan ikki yildan biriga mos bo'lishi shart.
5. Hujjatdan jami sahifalar soni va jami mualliflar sonini aniqlang.
6. Ballni hisoblamang va point uchun 0 qaytaring. Server ballni (jami sahifalar / 16) × 0.3 / jami mualliflar formulasi bilan hisoblaydi.

Qaror:
- Barcha majburiy talablar o'qiladigan rasmiy dalil bilan tasdiqlansa accepted qaytaring.
- Tarjima emasligi, maqsad tili ruxsat etilmagani, ISBN yo'qligi yoki nashr yili ruxsat etilmagani aniq bo'lsa cancelled qaytaring.
- Tarjima dalili, ISBN, nashr yili, sahifalar soni yoki mualliflar soni xira, kesilgan yoki qarama-qarshi bo'lsa checking qaytaring; ma'lumotni taxmin qilmang.
PROMPT;

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
- Hujjat o‘zbek, qoraqalpoq yoki rus tilida bo‘lishidan qat’i nazar, aynan tarjima ekanini tekshiring.
- Hujjatdan asl asarning manba tilini va tarjima qilingan nashrning maqsad tilini alohida aniqlang. source_language uchun manba tilining nomini, target_language uchun faqat uz, kaa yoki ru kodlaridan birini qaytaring.
- Manba va maqsad tillari bir xil bo‘lishi mumkin emas. Maqsad tili uz, kaa yoki ru bo‘lmasa resurs mezonga mos emas.
- Tarjima ekanini titul varaq, bibliografik ma’lumot, nashr ruxsatnomasi yoki boshqa o‘qiladigan rasmiy dalil aniq tasdiqlashi shart. Faqat foydalanuvchi yozgan metadata yoki fayl nomiga ishonmang.
- ISBN, nashr yili, jami sahifalar soni va jami mualliflar sonini hujjatning o‘zidan aniqlang. Ushbu maydonlardan birortasini taxmin qilmang.
- Ballni hisoblamang: point uchun 0 qaytaring. Server (jami sahifalar / 16) × 0.3 / jami mualliflar formulasini qo‘llaydi.
- Tarjima emasligi aniq bo‘lsa, is_translation false, cancelled statusi va 0 ball qaytaring. Tarjima holati yoki tillardan biri o‘qilmasa, checking statusi qaytaring.
- accepted faqat is_translation true bo‘lsa, source_language aniq bo‘lsa, target_language uz, kaa yoki ru bo‘lsa hamda manba va maqsad tillari o‘zaro farqli bo‘lsa mumkin.
PROMPT;
    }
}
