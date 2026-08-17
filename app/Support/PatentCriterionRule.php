<?php

namespace App\Support;

class PatentCriterionRule
{
    public const CODE = '3.1.8';

    public const DESCRIPTION_UZ = 'Faqat rasmiy patent hujjati qabul qilinadi. DGU, EHM dasturi yoki ma’lumotlar bazasini ro‘yxatdan o‘tkazish guvohnomasi, mualliflik guvohnomasi, patent arizasi va boshqa hujjatlar qabul qilinmaydi. Patent berilgan sana umumiy KPI hisobot davriga mos bo‘lishi va resursni yuklagan professor-o‘qituvchi patent mualliflari orasida ko‘rsatilgan bo‘lishi shart. Huquq egasi universitet yoki universitet o‘qituvchisi bo‘lishi kerak. Tasdiqlangan har bir patent uchun ilmiy darajalilarga 3 ball, qolgan toifalarga 4 ball beriladi. Ball mualliflar soniga bo‘linmaydi.';

    public const DESCRIPTION_KAA = 'Tek ǵana rásmiy patent hújjeti qabıl etiledi. DGU, EEM programması yamasa maǵlıwmatlar bazasın dizimnen ótkeriw gúwalıǵı, avtorlıq gúwalıǵı, patent arızası hám basqa hújjetler qabıl etilmeydi. Patent berilgen sáne ulıwma KPI esabat dáwirine sáykes bolıwı hám resurstı júklegen professor-oqıtıwshı patent avtorları arasında kórsetiliwi shárt. Tastıyıqlanǵan hár bir patent ushın ilimiy dárejelilerge 3 ball, qalǵan kategoriyalarǵa 4 ball beriledi. Ball avtorlar sanına bólinbeydi.';

    public const DESCRIPTION_RU = 'Принимается только официальный патент. ДГУ, свидетельство о регистрации программы для ЭВМ или базы данных, авторское свидетельство, заявка на патент и другие документы не принимаются. Дата выдачи патента должна соответствовать общему отчетному периоду KPI, а загрузивший ресурс преподаватель должен быть указан среди авторов патента. За каждый подтвержденный патент обладателям ученой степени начисляется 3 балла, остальным категориям — 4 балла. Балл не делится на количество авторов.';

    public const DESCRIPTION_EN = 'Only an official patent is accepted. DGU, software or database registration certificates, copyright certificates, patent applications, and other documents are not accepted. The patent grant date must fall within the common KPI reporting period, and the teacher who submitted the resource must be listed among the patent authors. Each approved patent receives 3 points for academic degree holders and 4 points for all other categories. The score is not divided by the number of authors.';

    /** @return array{uz: string, kaa: string, ru: string, en: string} */
    public static function descriptions(): array
    {
        return [
            'uz' => self::DESCRIPTION_UZ,
            'kaa' => self::DESCRIPTION_KAA,
            'ru' => self::DESCRIPTION_RU,
            'en' => self::DESCRIPTION_EN,
        ];
    }

    public const PROMPT = <<<'PROMPT'
Siz 3.1.8 mezoni bo'yicha patent hujjatini tekshiruvchi qat'iy AI yordamchisiz.

VAZIFA:
- Hujjat aynan vakolatli davlat organi bergan rasmiy PATENT ekanini tekshiring. Hujjat nomi, patent raqami va patent berilgan sana aniq ko'rinishi kerak.
- DGU, EHM uchun dastur yoki ma'lumotlar bazasini ro'yxatdan o'tkazish guvohnomasi, mualliflik guvohnomasi, patent arizasi, talabnoma, ekspertiza qarori va patent bo'lmagan boshqa hujjatlar qat'iyan qabul qilinmaydi.
- `author_full_name` tizim bergan ishonchli foydalanuvchi ismidir. Aynan shu shaxs patent hujjatidagi mualliflar yoki ixtirochilar ro'yxatida bo'lishi shart. Faqat patent egasi, huquq egasi, tashkilot xodimi yoki hujjatni yuklagan shaxs bo'lish yetarli emas.
- Patent berilgan sanani hujjatdan toping va resource_date maydonida YYYY-MM-DD formatida qaytaring. Sana tizim bergan umumiy report_period oralig'ida bo'lishi shart.
- Ballni tanlamang, hisoblamang va mualliflar soniga bo'lmang. Tasdiqlangan har bir patent uchun server foydalanuvchining ishonchli HEMIS toifasi bo'yicha ilmiy darajalilarga 3 ball, qolgan barcha toifalarga 4 ball beradi.

QAROR:
- Hujjat aynan patent bo'lsa, `author_full_name` patent mualliflari orasida aniq topilsa va patent sanasi ruxsat etilgan davrga mos bo'lsa accepted qaytaring.
- Hujjat xira, kesilgan yoki patent turi, patent raqami, mualliflar ro'yxati, foydalanuvchining muallifligi yoxud patent sanasi ishonchli aniqlanmasa checking qaytaring.
- Hujjat DGU yoki patent bo'lmagan boshqa hujjat bo'lsa, `author_full_name` mualliflar orasida bo'lmasa yoxud patent sanasi ruxsat etilgan davrdan tashqarida bo'lsa cancelled qaytaring.

Point maydoniga har qanday statusda 0 yozing: yakuniy ballni server hisoblaydi. Author_count qaytarmang. Reason ichida patent turi va raqami, foydalanuvchining mualliflar orasida topilgani hamda patent berilgan sanani qisqa yozing.
PROMPT;
}
