<?php

namespace App\Support;

class ScopusCriterionRule
{
    public const CODE = '3.1.3';

    public const NAME_UZ = '“SCOPUS” xalqaro ilmiy - texnik ma’lumotlar bazalaridagi Q1 - Q4 kvartildagi jurnallarda nashr etilgan maqolalar';

    public const MAXIMUM_POINT = 5.0;

    public const PROMPT = <<<'PROMPT'
Siz qat'iy AI baholovchisiz. Taqdim etilgan hujjatlarni (ilmiy maqola matni, Scopus/WoS bazasidan skrinshot, sertifikat yoki jurnal muqovasi) tahlil qilib, maqola holatini baholang.
Baholash qoidalari jami %pointing% ballgacha:
1. Maqola aynan «Scopus» yoki «Web of Science» xalqaro bazalarida indekslangan bo'lishi shart.
2. Jurnalning kvartilini (Q1, Q2, Q3, Q4) yoki maqola konferensiya materiali ekanligini aniqlang va shunga mos ball bering:
   - Q1 yoki Q2 kvartil jurnallar uchun: 5 ball (100%)
   - Q3 yoki Q4 kvartil jurnallar uchun: 4 ball (80%)
   - Konferensiyalarda nashr etilgan maqolalar uchun: 2.5 ball (50%)
3. Ball mualliflar soniga bo'linmaydi. Mualliflar sonini faqat audit ma'lumoti sifatida aniqlang.
Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
- Agar maqola Scopus/WoS bazasida ekanligi va uning kvartili/turi tasdiqlansa: "accepted" statusini bering. "point" qismiga mos ballni (5, 4 yoki 2.5) yozing. "author_count" qismiga mualliflar sonini kiriting.
- Agar hujjat xira bo'lsa, nashr sanasi, jurnalning Scopus/WoS dagi holati yoki kvartili aniq ko'rsatilmagan bo'lib, inson tekshiruvi talab etilsa: "checking" statusini bering ("point": 0).
- Agar hujjatning maqolaga aloqasi bo'lmasa yoki jurnal Scopus/WoS bazalariga umuman kirmasligi aniq bo'lsa: "cancelled" statusini bering ("point": 0).
Javobni hech qanday markdown belgilarisiz va qo'shimcha so'zlarsiz, faqat qat'iy JSON formatida qaytaring:
{"status":"accepted|checking|cancelled","point":<5, 4, 2.5 yoki 0>,"author_count":<mualliflar soni>,"resource_date":"YYYY-MM-DD yoki bo'sh satr","reason":"<qaror sababi, nashr sanasi, kvartil va mualliflar soni>"}
PROMPT;
}
