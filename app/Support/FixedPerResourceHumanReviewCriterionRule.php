<?php

namespace App\Support;

use App\Data\AiEvaluationResult;

class FixedPerResourceHumanReviewCriterionRule
{
    public const TWO_ONE_TWO_CODE = '2.1.2';

    public const FOUR_ONE_TWO_CODE = '4.1.2';

    /** @var array<string, array<string, float>> */
    private const POINTS = [
        self::TWO_ONE_TWO_CODE => [
            'hold_degrees' => 2.0,
            'no_degrees' => 2.0,
            'physical' => 2.0,
        ],
        self::FOUR_ONE_TWO_CODE => [
            'hold_degrees' => 1.0,
            'no_degrees' => 1.0,
            'foreign_lang' => 1.0,
            'physical' => 2.0,
        ],
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
        '3.1.11' => [
            'hold_degrees' => 3.0,
            'no_degrees' => 4.0,
            'foreign_lang' => 4.0,
            'physical' => 4.0,
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

    public static function twoOneTwoPrompt(): string
    {
        return <<<'PROMPT'
Siz 2.1.2 mezoni bo'yicha professor-o'qituvchi o'z mutaxassislik fanini xorijiy tilda olib borganini tekshiruvchi qat'iy AI yordamchisiz.

VAZIFA:
- Dars jadvali, o'quv dasturi, kafedra ma'lumotnomasi, buyruq yoki boshqa rasmiy hujjatda professor-o'qituvchi o'z mutaxassislik fanini xorijiy tilda olib borgani aniq tasdiqlanishi kerak.
- Ballni tanlamang va hisoblamang. Mos toifadagi foydalanuvchining tasdiqlangan resursiga server qat'iy 2 ball beradi.
- `foreign_lang` baholash toifasidagi foydalanuvchilar bu mezonga resurs yuklay olmaydi; ushbu cheklovni server foydalanuvchining ishonchli HEMIS toifasi bo'yicha qo'llaydi. Hujjat matniga qarab foydalanuvchi toifasini taxmin qilmang.

QAROR:
- Mutaxassislik fani xorijiy tilda olib borilgani aniq tasdiqlansa accepted qaytaring.
- Hujjat xira, kesilgan, fan, o'qituvchi yoki dars tili noaniq bo'lsa checking qaytaring.
- Dars xorijiy tilda olib borilmagani yoki hujjat mezonga aloqasizligi aniq bo'lsa cancelled qaytaring.

Point maydoniga har qanday statusda 0 yozing: yakuniy ballni server hisoblaydi. Reason ichida fan, xorijiy til va dars olib borilganini tasdiqlovchi dalilni qisqa yozing.
PROMPT;
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

    public static function fourOneTwoPrompt(): string
    {
        return <<<'PROMPT'
Siz 4.1.2 mezoni bo'yicha davlat hokimiyati yoki boshqaruvi organining murojaatiga asosan ilmiy-amaliy taklif tayyorlanganini va professor-o'qituvchi unda ishtirok etganini tekshiruvchi qat'iy AI yordamchisiz.

VAZIFA:
- Davlat hokimiyati yoki boshqaruvi organidan kelgan rasmiy xat yoxud murojaat mavjudligini tekshiring. Vazirlik, hokimlik va davlat agentligi shunday organlarga misol bo'ladi; xususiy tashkilot murojaati qabul qilinmaydi.
- Murojaat mazmuniga javoban ilmiy-amaliy taklif yoki ishlanma tayyorlanganini tekshiring.
- Resursni taqdim etgan professor-o'qituvchining taklifni tayyorlashdagi yoki jarayondagi ishtiroki aniq tasdiqlanishi kerak.
- Ballni tanlamang va hisoblamang. Tasdiqlangan resursga server foydalanuvchining ishonchli HEMIS toifasi bo'yicha jismoniy madaniyat yo'nalishi uchun 2 ball, qolgan toifalar uchun 1 ball beradi.

QAROR:
- Rasmiy davlat organi murojaati, ilmiy-amaliy taklif va professor-o'qituvchining ishtiroki aniq tasdiqlansa accepted qaytaring.
- Hujjat xira, kesilgan, tashkilotning davlat organi ekani, murojaat mazmuni, taklif yoki professor-o'qituvchining ishtiroki noaniq bo'lsa checking qaytaring.
- Murojaat xususiy tashkilotdan bo'lsa, ilmiy-amaliy taklif yoki professor-o'qituvchining ishtiroki mavjud emasligi aniq bo'lsa yoxud hujjat mezonga aloqasiz bo'lsa cancelled qaytaring.

Point maydoniga har qanday statusda 0 yozing: yakuniy ballni server hisoblaydi. Reason ichida murojaat qilgan davlat organi, taklif mazmuni va professor-o'qituvchining ishtirokiga oid dalilni qisqa yozing.
PROMPT;
    }

    public static function threeOneElevenPrompt(): string
    {
        return <<<'PROMPT'
Siz 3.1.11 mezoni bo‘yicha professor-o‘qituvchi rahbarligidagi talabaning respublika yoki xalqaro olimpiada, nufuzli tanlovda sovrinli o‘rin olgani, mukofot (diplom) yoki stipendiyaga sazovor bo‘lganini tekshiruvchi qat’iy AI yordamchisiz.

VAZIFA:
- Talabaning yutug‘i yoki stipendiat bo‘lganini tasdiqlovchi rasmiy diplom, sertifikat, buyruq yoki boshqa ishonchli hujjatni tekshiring.
- Taqdim etgan professor-o‘qituvchi aynan shu talabaga bevosita rahbar bo‘lganini buyruq, kengash qarori yoki boshqa rasmiy hujjat orqali tekshiring.
- Respublika va xalqaro daraja o‘rtasida ball farqi yo‘q. Ballni o‘zingiz tanlamang va hisoblamang.
- Tasdiqlangan har bir resurs uchun server foydalanuvchi toifasiga ko‘ra ilmiy darajalilarga 3 ball, qolgan barcha toifalarga 4 ball beradi.

QAROR:
- Talabaning yutug‘i yoki stipendiatligi hamda professor-o‘qituvchining rahbarligi aniq tasdiqlansa accepted qaytaring.
- Hujjat xira, kesilgan, talaba yoki rahbar ma’lumoti noaniq yoxud majburiy dalillardan biri yetishmasa checking qaytaring.
- Hujjat mezonga aloqasizligi, talabaning yutug‘i tasdiqlanmagani yoki professor-o‘qituvchi unga rahbar emasligi aniq bo‘lsa cancelled qaytaring.

Point maydoniga har qanday statusda 0 yozing: yakuniy ballni server ishonchli foydalanuvchi toifasi asosida avtomatik hisoblaydi. Reason ichida talaba yutug‘i yoki stipendiatligi va rahbarlikni tasdiqlovchi dalillarni qisqa yozing. Javob maydonlari va resource_date qiymatini tizim promptidagi qat’iy JSON formatiga muvofiq qaytaring.
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
