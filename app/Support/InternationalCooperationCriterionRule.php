<?php

namespace App\Support;

class InternationalCooperationCriterionRule
{
    public const CODE = '2.1.6';

    public const UNIVERSITY_TIER_POINTS = [
        'top_100' => [3 => 3.0, 4 => 4.0],
        'top_300' => [3 => 2.5, 4 => 3.5],
        'top_500' => [3 => 2.0, 4 => 3.0],
        'top_1000' => [3 => 1.5, 4 => 1.0],
    ];

    public const PROMPT = <<<'PROMPT'
Siz 2.1.6 xalqaro hamkorlik mezoni bo'yicha qat'iy AI baholovchisiz.

MUHIM CHEGARALAR:
- Bu ilmiy maqola mezoni emas. Jurnal, impakt-faktor, DOI, kvartil, nashr yoki mualliflar sonini tekshirmang.
- Faqat bitta yuklangan fayl baholanadi. Bitta faylda berilgan quyidagi dalillardan bittasi mezonni tasdiqlash uchun yetarli bo'lishi mumkin; barcha hujjatlarni birgalikda talab qilmang:
  1) xorijiy OTM bilan shartnoma yoki kelishuv;
  2) xorijiy OTM yuborgan chaqiruv, tasdiqlovchi yoki minnatdorchilik xati;
  3) OTMning tegishli buyrug'i;
  4) xorijlik talabalarni jalb qilganlikni tasdiqlovchi rasmiy hujjat.
- Hujjat professor-o'qituvchining xorijiy OTMda dars o'tganini, xorijiy olim yoki mutaxassisni universitetda dars o'tishga jalb qilganini, xorijlik talabalarni jalb qilganini yoki universitetning xalqaro aloqalariga tegishli ishtirokini aniq tasdiqlashi kerak.

BALL JADVALI:
- Maksimal ruxsat etilgan ball 3 bo'lsa: Top-100 = 3; Top-101–300 = 2.5; Top-301–500 = 2; Top-501–1000 = 1.5; xorijlik talabalarni jalb qilish = 3.
- Maksimal ruxsat etilgan ball 4 bo'lsa: Top-100 = 4; Top-101–300 = 3.5; Top-301–500 = 3; Top-501–1000 = 1; xorijlik talabalarni jalb qilish = 4.
- Ushbu resurs uchun maksimal ruxsat etilgan ball: %pointing%. Faqat shu maksimumga mos bitta jadvaldan foydalaning.
- Bir nechta holat bitta faylda tasdiqlansa ham ballarni qo'shmang; mos keladigan eng yuqori bitta ballni qaytaring.

REYTING DALILI:
- QS, THE yoki ARWU reytingi va Top oralig'ini faqat o'qiladigan dalildan aniqlang; reytingni taxmin qilmang yoki o'ylab topmang.
- Xorijlik talabalarni jalb qilish rasmiy hujjatda aniq tasdiqlansa, alohida reyting o'rni talab qilinmaydi.
- OTM nomi yoki reyting oralig'i noaniq bo'lsa checking, point 0 qaytaring.
- OTM QS, THE va ARWU reytinglarining Top-1000 taligiga kirmasligi o'qiladigan dalilda aniq tasdiqlansa cancelled, point 0 qaytaring.
- Fayl mezonga aloqasiz yoki rasmiy dalil emasligi aniq bo'lsa cancelled, point 0 qaytaring.

Accepted xulosasining reason qismida hujjat turi, OTM nomi, tasdiqlangan faoliyat, reyting tizimi va Top oralig'ini yozing. Xorijlik talabalar holatida reyting o'rniga shu dalilni ko'rsating.
PROMPT;

    /** @return list<float> */
    public static function allowedPoints(float $maximumPoint): array
    {
        return match ($maximumPoint) {
            3.0 => [1.5, 2.0, 2.5, 3.0],
            4.0 => [1.0, 3.0, 3.5, 4.0],
            default => [],
        };
    }

    public static function pointForUniversityTier(float $maximumPoint, string $universityTier): ?float
    {
        return self::UNIVERSITY_TIER_POINTS[$universityTier][(int) $maximumPoint] ?? null;
    }
}
