<?php

namespace App\Support;

class OakArticleCriterionRule
{
    public const CODE = '3.1.1';

    public const WITH_DEGREE_BASE_POINT = 0.5;

    public const WITHOUT_DEGREE_BASE_POINT = 0.75;

    public const DESCRIPTION_UZ = 'Har bir tasdiqlangan resurs uchun ilmiy darajaga ega foydalanuvchiga 0,5 ball, ilmiy darajaga ega bo‘lmagan foydalanuvchiga 0,75 ball beriladi. Ball maqoladagi jami mualliflar soniga teng bo‘linadi.';

    public const PROMPT = <<<'PROMPT'
Siz qat'iy AI baholovchisiz. Taqdim etilgan hujjatlarni (ilmiy maqola matni, jurnal muqovasi, mundarija yoki nashr ma'lumotnomasi) tahlil qilib, maqola holatini baholang.
Baholash qoidalari:
1. Maqola OAK (Oliy attestatsiya komissiyasi) ro'yxatiga kiritilgan xorijiy yoki mahalliy ilmiy jurnalda chop etilganligi tasdiqlanishi kerak.
2. Jurnal «Web of Science» yoki «Scopus» bazalariga kirmasligi shart.
3. Maqoladagi jami mualliflar sonini aniqlang. Ballni bo'lishni dastur bajaradi.
4. Maqolaning nashr sanasini YYYY-MM-DD yoki faqat YYYY formatida aniqlang.
5. Jurnal sonini aniqlang va publication_issue maydonida butun son sifatida qaytaring. Aniqlanmasa 0 qaytaring.
6. %period_end_year%-yildagi maqolalar jurnal sonidan qat'i nazar qabul qilinadi. %period_start_year%-yildagi maqolalar faqat jurnalning 3 yoki 4-sonida chop etilgan bo'lsa qabul qilinadi.
Tahlil natijasiga ko'ra:
- Talablar tasdiqlansa: "accepted", "point": %pointing%, "author_count": jami mualliflar soni, "publication_issue": jurnal soni va "resource_date": nashr sanasi yoki yili.
- Hujjat xira yoki ma'lumot yetarli bo'lmasa: "checking", "point": 0.
- Maqola mezonga mos kelmasa yoki jurnal Scopus/Web of Science bazasida bo'lsa: "cancelled", "point": 0.
Faqat qat'iy JSON obyekt qaytaring:
{"status":"accepted|checking|cancelled","point":<%pointing% yoki 0>,"author_count":<jami mualliflar soni>,"publication_issue":<jurnal soni yoki 0>,"resource_date":"YYYY-MM-DD, YYYY yoki bo'sh satr","reason":"<qaror sababi, jurnal, nashr yili, jurnal soni va mualliflar soni>"}
PROMPT;
}
