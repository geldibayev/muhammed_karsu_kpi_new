<?php

namespace App\Support;

class InternationalCooperationCriterionRule
{
    public const CODE = '2.1.6';

    public const UNIVERSITY_TIER_POINTS = [
        'top_100' => [3 => 3.0, 4 => 4.0],
        'top_300' => [3 => 2.25, 4 => 3.0],
        'top_500' => [3 => 1.5, 4 => 2.0],
        'top_1000' => [3 => 0.75, 4 => 1.0],
        'foreign_students' => [3 => 3.0, 4 => 4.0],
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

TOP DARAJASI:
- Ballni hisoblamang va point maydoniga qat'iy 0 yozing. Server ballni foydalanuvchi toifasi va university_tier asosida hisoblaydi.
- Aniq reyting o'rniga ko'ra university_tier qiymatini tanlang:
  - 1–100: top_100;
  - 101–300: top_300;
  - 301–500: top_500;
  - 501–1000: top_1000;
  - xorijlik talabalarni jalb qilish tasdiqlansa: foreign_students.
- Top-100 — maksimal ballning 100%; Top-101–300 — 75%; Top-301–500 — 50%; Top-501–1000 — 25%; xorijlik talabalarni jalb qilish — 100%.
- Jismoniy madaniyat va xorijiy tillar toifasi uchun maksimal ball 4, boshqa toifalar uchun 3 ball.
- Bir nechta holat tasdiqlansa ham ularni qo'shmang; eng yuqori foizli bitta university_tier qaytaring.

REYTING DALILI:
- Hujjatda Al-Farabi nomidagi Qozog'iston Milliy Universiteti (Al-Farabi Kazakh National University, KazNU) aniq ko'rsatilsa, alohida reyting dalilisiz university_tier uchun top_300 qaytaring. Bu 75% toifa; point baribir 0 bo'lsin, ballni server hisoblaydi.
- QS, THE yoki ARWU reytingi va Top oralig'ini faqat o'qiladigan dalildan aniqlang; reytingni taxmin qilmang yoki o'ylab topmang.
- Xorijlik talabalarni jalb qilish rasmiy hujjatda aniq tasdiqlansa, alohida reyting o'rni talab qilinmaydi.
- OTM nomi yoki reyting oralig'i noaniq bo'lsa checking, university_tier uchun unknown va point 0 qaytaring.
- OTM QS, THE va ARWU reytinglarining Top-1000 taligiga kirmasligi o'qiladigan dalilda aniq tasdiqlansa cancelled, university_tier uchun outside_top_1000 va point 0 qaytaring.
- Fayl mezonga aloqasiz yoki rasmiy dalil emasligi aniq bo'lsa cancelled, point 0 qaytaring.

Accepted xulosasining reason qismida hujjat turi, OTM nomi, tasdiqlangan faoliyat, reyting tizimi va Top oralig'ini yozing. Xorijlik talabalar holatida reyting o'rniga shu dalilni ko'rsating. Reason ichida ball, foiz yoki arifmetik hisob-kitob yozmang.
PROMPT;

    public const DESCRIPTION_UZ = 'QS, THE yoki ARWU reytingidagi xorijiy OTM bilan xalqaro hamkorlik Top darajasiga ko‘ra baholanadi: Top-1–100 — 100%, Top-101–300 — 75%, Top-301–500 — 50%, Top-501–1000 — 25%. Top-1000 dan past o‘rin uchun ball berilmaydi. Xorijlik talabalarni jalb qilish — 100%. Jismoniy madaniyat va xorijiy tillar toifasi uchun maksimal ball 4, boshqa toifalar uchun 3 ball.';

    public static function tierForRank(int $rank): ?string
    {
        return match (true) {
            $rank < 1 => null,
            $rank >= 1 && $rank <= 100 => 'top_100',
            $rank <= 300 => 'top_300',
            $rank <= 500 => 'top_500',
            $rank <= 1000 => 'top_1000',
            default => 'outside_top_1000',
        };
    }

    public static function maximumPointForEvaluationCategory(?string $evaluationCategory): float
    {
        return in_array($evaluationCategory, ['foreign_lang', 'physical'], true) ? 4.0 : 3.0;
    }

    public static function pointForUniversityTier(float $maximumPoint, string $universityTier): ?float
    {
        return self::UNIVERSITY_TIER_POINTS[$universityTier][(int) $maximumPoint] ?? null;
    }

    public static function percentageForUniversityTier(string $universityTier): ?int
    {
        return match ($universityTier) {
            'top_100', 'foreign_students' => 100,
            'top_300' => 75,
            'top_500' => 50,
            'top_1000' => 25,
            default => null,
        };
    }
}
