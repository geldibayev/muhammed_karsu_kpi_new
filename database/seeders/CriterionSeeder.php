<?php

namespace Database\Seeders;

use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\CriterionYear;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CriterionSeeder extends Seeder
{
    public function run(): void
    {
        $criteria = [
            [
                'main' => [
                    'name' => [
                        'uz' => 'O‘quv-uslubiy ishlar va o‘qitish sifati',
                        'kaa' => 'Oqíw-metodikalíq jumíslar hám oqítíw sapasí',
                        'ru' => 'Учебно-методическая работа и качество обучения',
                        'en' => 'Educational and methodological work and teaching quality',
                    ],
                    'report_id' => 1, 'upload' => '1', 'status' => '1',
                ],
                'children' => [
                    [
                        'name' => [
                            'uz' => 'Sifatli o‘quv kontentlari (videodarslar, videoroliklar, taqdimotlar)',
                            'kaa' => 'Sapalı oqıw kontentleri (videosabaqlar, videorolikler, prezentaciyalar)',
                            'ru' => 'Качественный образовательный контент (видеоуроки, видеоролики, презентации)',
                            'en' => 'High-quality educational content (video lessons, videos, presentations)',
                        ],
                        'desc' => [
                            'uz' => 'O‘quv kontentlari “YouTube” havola kiritiladi. <br>Videodarslar-1,5 ball, videoroliklar-1 ball, taqdimotlar-0,5 ball.',
                            'kaa' => 'Oqıw kontentleri "YouTube" siltemesi qosıladı. <br>Videosabaqlar-1,5 ball, videorolikler-1 ball, prezentaciyalar-0,5 ball.',
                            'ru' => 'Учебный контент Введите ссылку на "YouTube." <br>Видеоуроки - 1,5 балла, видеоролики - 1 балл, презентации - 0,5 балла.',
                            'en' => 'Educational content will be linked to "YouTube." <br>Video lessons - 1.5 points, video clips - 1 point, presentations - 0.5 points.',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'manual',
                        'template' => '0',
                        'res_type' => 'url',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'foreign_lang' => 4,
                            'hold_degrees' => 3,
                            'no_degrees' => 6,
                            'physical' => 4,
                        ],
                        'year' => 2025,
                        'formula_id' => 1,
                        'ai_model' => 'gemini-2.5-pro',
                        'ai_prompt' => "Siz qat'iy AI baholovchisiz. Taqdim etilgan YouTube havolasi va uning ta'rifi/nomini tahlil qilib, o'quv kontenti turini aniqlang.
                        Baholash qoidalari:
                        1. Videodars = 1.5 ball
                        2. Videorolik = 1.0 ball
                        3. Taqdimot = 0.5 ball.

                        Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
                        - Agar havola haqiqiy, ta'limga oid bo'lsa va kontent turi aniq bo'lsa: \"accepted\" qiling va unga mos ballni \"point\" ga yozing.
                        - Agar havoladagi ma'lumot xira, ta'rifi tushunarsiz yoki kontent turini aniq ajratib bo'lmasa (inson ko'rib chiqishi kerak bo'lsa): \"checking\" qiling.
                        - Agar havola ishlamasa, ta'limga umuman aloqasi bo'lmasa yoki shartlarga zid bo'lsa: \"cancelled\" qiling.

                        Javobni hech qanday qo'shimcha matnlar va markdown belgilarsiz (```json...``` kabi emas), faqatgina quyidagi qat'iy JSON formatida qaytaring:
                        {\"status\": \"accepted|checking|cancelled\", \"point\": <faqat raqam, masalan 1.5>, \"reason\": \"<Qabul qilingan qarorning qisqacha sababi, nima uchun aynan shu ball yoki status berilganligi>\"}"
                    ],
                    [
                        'name' => [
                            'uz' => 'Nashr etilgan darslik',
                            'kaa' => 'Basıp shıǵarılǵan sabaqlíq',
                            'ru' => 'Опубликованный учебник',
                            'en' => 'Published textbook',
                        ],
                        'desc' => [
                            'uz' => 'Belgilangan tartib va talablar asosida tayyorlanib, chop qilinganligi hamda ushbu darslik bo‘yicha vazirlik yoki universitetning o‘quv adabiyotlariga nashr ruxsatnomasi, ISBN raqami asosida aniqlanadi. Mualliflik ulushi inobatga olinadi. Baholash usuli: har bir bosma tabog‘i uchun 0,4 ball.',
                            'kaa' => 'Belgilengen tártip hám talaplar tiykarında tayarlanıp, basıp shıǵarılǵanlıǵı hám de usı sabaqlıq boyınsha ministrlik yamasa universitettiń oqıw ádebiyatlarına baspa ruxsatnaması, ISBN nomeri tiykarında anıqlanadı. Avtorlıq úlesi esapqa alınadı. Bahalaw usili: hár bir baspa tabaq ushin 0,4 ball.',
                            'ru' => 'Подготовка и издание учебника в соответствии с установленным порядком и требованиями определяется на основании разрешения министерства или университета на публикацию учебной литературы по данному учебнику, номера ISBN. Авторский вклад учитывается. Метод оценки: 0,4 балла за каждый печатный лист.',
                            'en' => 'The preparation and publication of the textbook in accordance with the established procedure and requirements, as well as the publication permit for educational literature from the ministry or university, are determined based on the ISBN number. The author’s contribution is taken into account. Assessment method: 0.4 points for each printed sheet.',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'ai',
                        'template' => '1',
                        'res_type' => 'all',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 6,
                            'no_degrees' => 5,
                            'foreign_lang' => 5,
                            'physical' => 5,
                        ],
                        'year' => 2025,
                        'formula_id' => 2,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => "Siz qat'iy AI baholovchisiz. Taqdim etilgan hujjatlarni (darslik muqovasi, ISBN sahifasi, nashr ruxsatnomasi, kitobning texnik ma'lumotlari) tahlil qiling.
                        Baholash qoidalari jami %pointing% ballgacha:
                        1. Hujjatda darslikning ISBN raqami aniq ko'rsatilgan bo'lishi shart.
                        2. Hujjatda Vazirlik yoki Universitetning o'quv adabiyotlariga nashr ruxsatnomasi mavjud bo'lishi shart.
                        3. Baholash usuli: har bir bosma tabog'i (printed sheet/bosma taboq) uchun 0.4 ball. Hujjatdan kitobning jami bosma tabog'i hajmini aniqlang.
                        4. Mualliflik ulushini aniqlash uchun mualliflar sonini aniqlang va umumiy ballni shunga qarab taqsimlang.

                        Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
                        - Agar ISBN, nashr ruxsatnomasi va bosma tabog'i aniq tasdiqlansa: \"accepted\" statusini bering. \"point\" qismiga (bosma_tabog'i * 0.4) qiymatini mualliflar soniga bo'lib yozing. \"author_count\" ga mualliflar sonini kiriting.
                        - Agar hujjatlar xira bo'lsa, ISBN yoki ruxsatnomani o'qib bo'lmasa, yoki bosma tabog'i hajmi ko'rsatilmagan bo'lib, inson (administrator) tekshiruvi talab etilsa: \"checking\" statusini bering (\"point\": 0).
                        - Agar hujjatning darslikka aloqasi bo'lmasa, ISBN yoki ruxsatnoma topilmasa: \"cancelled\" statusini bering (\"point\": 0).

                        Javobni hech qanday markdown belgilarisiz (```json...``` kabi emas) va qo'shimcha so'zlarsiz, faqatgina quyidagi qat'iy JSON formatida qaytaring:
                        {\"status\": \"accepted|checking|cancelled\", \"point\": <raqam>, \"author_count\": <mualliflar soni raqamda>, \"reason\": \"<Qabul qilingan qarorning sababi, bosma tabog'i hajmi va ruxsatnoma haqida izoh>\"}",
                    ],
                    [
                        'name' => [
                            'uz' => 'Nashr etilgan o‘quv qo‘llanma',
                            'kaa' => 'Basıp shıǵarılǵan oqıw qollanba',
                            'ru' => 'Изданное учебное пособие',
                            'en' => 'Published study guide',
                        ],
                        'desc' => [
                            'uz' => 'Belgilangan tartib va talablar asosida tayyorlanib, chop qilinganligi hamda ushbu o‘quv qo‘llanma bo‘yicha vazirlik yoki universitetning o‘quv adabiyotlariga nashr ruxsatnomasi, ISBN raqami asosida aniqlanadi. Mualliflik ulushi inobatga olinadi. Baholash usuli: har bir bosma tabog‘i uchun 0,3 ball.',
                            'kaa' => 'Belgilengen tártip hám talaplar tiykarında tayarlanıp, basıp shıǵarılǵanlıǵı hám de usı oqıw qollanba boyınsha ministrlik yamasa universitettiń oqıw ádebiyatlarına baspa ruxsatnaması, ISBN nomeri tiykarında anıqlanadı. Avtorlıq úlesi esapqa alınadı. Bahalaw usili: hár bir baspa tabaq ushın 0,3 ball.',
                            'ru' => 'Подготовка и публикация в соответствии с установленным порядком и требованиями, а также разрешение министерства или университета на публикацию учебной литературы по данному учебному пособию определяются на основании номера ISBN. Авторский вклад учитывается. Метод оценки: 0,3 балла за каждый печатный лист.',
                            'en' => 'Preparation and publication in accordance with the established procedure and requirements, as well as the publication permit of the ministry or university for educational literature under this textbook, are determined based on the ISBN number. The author’s contribution is taken into account. Assessment method: 0.3 points for each printed sheet.',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'ai',
                        'template' => '1',
                        'res_type' => 'all',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 5,
                            'no_degrees' => 4,
                            'foreign_lang' => 4,
                            'physical' => 4,
                        ],
                        'year' => 2025,
                        'formula_id' => 2,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => "Siz qat'iy AI baholovchisiz. Taqdim etilgan hujjatlarni (o'quv qo'llanma muqovasi, ISBN sahifasi, nashr ruxsatnomasi, texnik ma'lumotlar) tahlil qiling.
                        Baholash qoidalari:
                        1. Hujjatda o'quv qo'llanmaning ISBN raqami va Vazirlik yoki Universitetning o'quv adabiyoti uchun nashr ruxsatnomasi mavjudligini tekshiring.
                        2. Baholash usuli: har bir bosma tabog'i uchun 0.3 ball. Hujjatdan kitobning jami bosma tabog'i hajmini aniqlang.
                        3. Mualliflik ulushini aniqlash uchun mualliflar sonini aniqlang va umumiy ballni shunga qarab taqsimlang.

                        Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
                        - Agar ISBN, nashr ruxsatnomasi va bosma tabog'i hajmi aniq tasdiqlansa: \"accepted\" statusini bering. \"point\" qismiga (bosma_tabog'i * 0.3 / mualliflar_soni) natijasini yozing. \"author_count\" ga mualliflar sonini kiriting.
                        - Agar hujjatlar xira bo'lsa, ISBN yoki ruxsatnomani o'qib bo'lmasa, yoxud bosma tabog'i hajmi ko'rsatilmagan bo'lib, inson (administrator) tekshiruvi talab etilsa: \"checking\" statusini bering (\"point\": 0).
                        - Agar hujjatning o'quv qo'llanmaga aloqasi bo'lmasa, ISBN yoki ruxsatnoma topilmasa: \"cancelled\" statusini bering (\"point\": 0).

                        Javobni hech qanday markdown belgilarisiz (```json...``` kabi emas) va qo'shimcha so'zlarsiz, faqatgina quyidagi qat'iy JSON formatida qaytaring:
                        {\"status\": \"accepted|checking|cancelled\", \"point\": <raqam>, \"author_count\": <mualliflar soni>, \"reason\": \"<Qabul qilingan qarorning sababi, bosma tabog'i hajmi va ruxsatnoma haqida qisqacha izoh>\"}",
                    ],
                    [
                        'name' => [
                            'uz' => 'Elektron darslik va o‘quv qo‘llanma yaratish yoki boshqa tillarda tarjima qilganligi',
                            'kaa' => 'Elektron sabaqlıq hám oqıw qollanbasın jaratıw yamasa basqa tillerge awdarǵanlıǵı',
                            'ru' => 'Создание электронных учебников и учебных пособий или их перевод на другие языки',
                            'en' => 'Creating electronic textbooks and teaching aids or translating them into other languages',
                        ],
                        'desc' => [
                            'uz' => 'Belgilangan tartib va talablar asosida elektron darslik va o‘quv qo‘llanma yaratilganligi yoki boshqa tillarda tarjima qilinganligi  tayyorlanib chop qilinganligi hamda ushbu o‘quv adabiyoti  bo‘yicha universitetning nashr ruxsatnomasi, ISBN raqami asosida aniqlanadi. Mualliflik ulushi inobatga olinadi.',
                            'kaa' => 'Belgilengen tártip hám talaplar tiykarında elektron sabaqlıq hám oqıw qollanba jaratılǵanlıǵı yamasa basqa tillerge awdarılǵanlıǵı tayarlanıp basıp shıǵarılǵanlıǵı hám de usı oqıw ádebiyatı boyınsha universitettiń baspa ruxsatnaması, ISBN nomeri tiykarında anıqlanadı. Avtorlıq úlesi esapqa alınadı.',
                            'ru' => 'Создание электронного учебника и учебного пособия на основе установленного порядка и требований или их перевод на другие языки, подготовка и издание, а также разрешение университета на публикацию данной учебной литературы определяются на основании номера ISBN. Авторский вклад учитывается.',
                            'en' => 'Based on the established procedure and requirements, the creation of electronic textbooks and teaching aids or their translation into other languages, as well as the preparation and publication of this educational literature, is determined on the basis of the university’s publication permit and ISBN number. The author’s contribution is taken into account.',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'ai',
                        'template' => '0',
                        'res_type' => 'all',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 5,
                            'no_degrees' => 4,
                            'foreign_lang' => 4,
                            'physical' => 4,
                        ],
                        'year' => 2025,
                        'formula_id' => 2,
                        'ai_model' => 'gemini-2.5-pro',
                        'ai_prompt' => "Siz qat'iy AI baholovchisiz. Taqdim etilgan hujjatni (elektron darslik, o'quv qo'llanma yoki uning tarjimasi) tahlil qiling va qoidalarga mosligini tekshiring.
                        Baholash qoidalari jami %pointing% ballgacha:
                        1. Hujjatda kitobning ISBN raqami aniq ko'rsatilgan bo'lishi shart.
                        2. Hujjatda universitetning nashr ruxsatnomasi (Kengash qarori yoki ruxsat beruvchi buyruq) mavjud bo'lishi shart.
                        3. Mualliflik ulushini aniqlash uchun hujjatdagi mualliflar (hammualliflar) sonini aniqlang. Asosiy ball 1 ball deb olinadi.

                        Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
                        - Agar hujjatda ISBN raqami VA universitet ruxsatnomasi mavjud bo'lsa, hujjat turi to'g'ri kelsa: \"accepted\" statusini bering. \"point\" ga 1 yozing (mualliflik ulushi dastur tomonidan bo'linadi) va \"author_count\" ga mualliflar sonini kiriting.
                        - Agar hujjat xira bo'lsa, sahifalar yetishmasa, ISBN yoki ruxsatnomani aniq o'qib bo'lmasa (inson ko'zdan kechirishi kerak bo'lsa): \"checking\" statusini bering.
                        - Agar hujjat umuman boshqa turdagi resurs bo'lsa, ISBN raqami yoki nashr ruxsatnomasi umuman topilmasa: \"cancelled\" statusini bering.

                        Javobni hech qanday markdown belgilarisiz (```json...``` kabi emas) va qo'shimcha so'zlarsiz, faqatgina quyidagi qat'iy JSON formatida qaytaring:
                        {\"status\": \"accepted|checking|cancelled\", \"point\": <raqam, masalan 1>, \"author_count\": <mualliflar soni raqamda>, \"reason\": \"<Qabul qilingan qarorning sababi, nima topildi yoki nima topilmadi>\"
                        ",
                    ],
                    [
                        'name' => [
                            'uz' => 'Ta’lim yo‘nalishlari va mutaxassisliklari bo‘yicha Fan (ishchi) dasturi va sillabus tayyorlash',
                            'kaa' => 'Bilimlendiriw baǵdarları hám qánigelikleri boyınsha Pán (jumısshı) baǵdarlaması hám sillabus tayarlaw',
                            'ru' => 'Подготовка предметной (рабочей) программы и силлабуса по направлениям и специальностям образования',
                            'en' => 'Preparation of the subject (working) program and syllabus for educational fields and specialties',
                        ],
                        'desc' => [
                            'uz' => 'Belgilangan tartib va talablar asosida tayyorlanib tasdiqlangan ta’lim yo‘nalishlari va mutaxassisliklari bo‘yicha Fan (ishchi) dasturi va sillabus tayyorlaganligi asosida aniqlanadi.',
                            'kaa' => 'Belgilengen tártip hám talaplar tiykarında tayarlanıp tastıyıqlanǵan tálim baǵdarları hám qánigelikleri boyınsha Pán (jumısshı) baǵdarlaması hám sillabus tayarlanǵanlıǵı tiykarında anıqlanadı.',
                            'ru' => 'Определяется на основе подготовки предметной (рабочей) программы и силлабуса по направлениям и специальностям образования, подготовленным и утвержденным в установленном порядке и требованиях.',
                            'en' => 'It is determined based on the preparation of a subject (working) program and syllabus for educational fields and specialties prepared and approved in accordance with the established procedure and requirements.',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'department',
                        'template' => '1',
                        'res_type' => 'all',
                        'upload' => '0', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 3,
                            'no_degrees' => 3,
                            'foreign_lang' => 3,
                            'physical' => 3,
                        ],
                        'year' => 2025,
                        'formula_id' => 1,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => null,
                    ],
                    [
                        'name' => [
                            'uz' => 'Talabalarning davomati va o‘zlashtirish ko‘rsatkichlari',
                            'kaa' => 'Studentlerdiń qatnasıwı hám ózlestiriw kórsetkishleri',
                            'ru' => 'Посещаемость и показатели успеваемости студентов',
                            'en' => 'Student attendance and academic performance',
                        ],
                        'desc' => [
                            'uz' => 'hemis.karsu.uz:<br>a) Hemisdan foydalanish bo‘yicha talabalar davomati<br>b) talabalar o‘zlashtirish ko‘rsatkichi',
                            'kaa' => 'hemis.karsu.uz:<br>a) Hemisden paydalanıw boyınsha studentler qatnasıwı<br>b) studentler ózlestiriw kórsetkishi',
                            'ru' => 'hemis.karsu.uz:<br>а) посещаемость студентов по использованию hemis<br>б) показатель успеваемости студентов',
                            'en' => 'hemis.karsu.uz:<br>a) Student attendance for using Hemis<br>b) student performance indicator',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'hemis:attendance',
                        'template' => '1',
                        'res_type' => 'all',
                        'upload' => '0', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 8,
                            'no_degrees' => 8,
                            'foreign_lang' => 8,
                            'physical' => 8,
                        ],
                        'year' => 2025,
                        'formula_id' => 1,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => null,
                    ],
                    [
                        'name' => [
                            'uz' => 'O‘qitish sifati darajasi',
                            'kaa' => 'Oqıtıw sapası dárejesi',
                            'ru' => 'Уровень качества образования',
                            'en' => 'Teaching quality level',
                        ],
                        'desc' => [
                            'uz' => 'Semestr yakuni bo‘yicha o‘tkaziladigan talabalar o‘rtasidagi anonim (ijtimoiy) so‘rovnoma natijalariga ko‘ra aniqlanadi',
                            'kaa' => 'Semestr juwmaǵı boyınsha ótkeriletuǵın studentler arasında anonim (sociallıq) sorawnama nátiyjelerine bola anıqlanadı.',
                            'ru' => 'Определяется по результатам анонимного (социального) опроса среди студентов, проводимого по итогам семестра.',
                            'en' => 'It is determined based on the results of an anonymous (social) survey conducted among students at the end of the semester.',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'hemis:vote',
                        'template' => '1',
                        'res_type' => 'all',
                        'upload' => '0', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 2,
                            'no_degrees' => 2,
                            'foreign_lang' => 2,
                            'physical' => 2,
                        ],
                        'year' => 2025,
                        'formula_id' => 2,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => null,
                    ],
                    [
                        'name' => [
                            'uz' => 'Mavjud va yangi laboratoriya ishini tayyorlash va joriy etish, shuningdek virtual laboratoriya ishini tayyorlash va joriy etish lobarotoriya va amaliy mashg‘ulotlar uchun uslubiy ko‘rsatmalar',
                            'kaa' => 'Bar hám jańa laboratoriyalıq jumıstı tayarlaw hám engiziw, sonday-aq, virtual laboratoriyalıq jumıstı tayarlaw hám engiziw laboratoriyalıq hám ámeliy shınıǵıwlar ushın metodikalıq kórsetpeler.',
                            'ru' => 'Подготовка и внедрение существующей и новой лабораторной работы, а также подготовка и внедрение виртуальной лабораторной работы методические указания для лабораторных и практических занятий',
                            'en' => 'Methodological guidelines for laboratory and practical sessions for the preparation and implementation of existing and new laboratory work, as well as the preparation and implementation of virtual laboratory work',
                        ],
                        'desc' => [
                            'uz' => 'Yangi laboratoriya va virtual laboratoriya-1 ball<br>Laboratoriya va amaliy mashg‘ulotlar uchun uslubiy qo‘llanma-1 ball',
                            'kaa' => 'Jańa laboratoriya hám virtual laboratoriya-1 ball<br>Laboratoriya hám ámeliy sabaqlar ushın metodikalıq qollanba-1 ball',
                            'ru' => 'Новая лаборатория и виртуальная лаборатория-1 балл<br>Методическое пособие для лабораторных и практических занятий-1 балл',
                            'en' => 'New laboratory and virtual laboratory - 1 point<br>Methodological guide for laboratory and practical sessions - 1 point',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'ai',
                        'template' => '0',
                        'res_type' => 'all',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 2,
                            'no_degrees' => 2,
                        ],
                        'year' => 2025,
                        'formula_id' => 1,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => "Siz qat'iy AI baholovchisiz. Taqdim etilgan hujjatlarni tahlil qilib, professor-o'qituvchining laboratoriya ishlariga oid faoliyatini baholang.
                        Baholash qoidalari jami %pointing% ballgacha:
                        1. Yangi laboratoriya ishi yoki virtual laboratoriya ishi tayyorlanganligi va joriy etilganligi tasdiqlansa: +1 ball.
                        2. Laboratoriya va amaliy mashg'ulotlar uchun uslubiy qo'llanma (yoki ko'rsatma) tayyorlanganligi tasdiqlansa: +1 ball.

                        Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
                        - Agar taqdim etilgan hujjatlarda yuqoridagi shartlarning kamida bittasi (yoki ikkalasi ham) aniq va tushunarli tasdiqlansa: \"accepted\" statusini bering. \"point\" qismiga tasdiqlangan holatlar yig'indisini (1 yoki 2 ball) yozing.
                        - Agar hujjatlar xira tushgan bo'lsa, matnni o'qish qiyin bo'lsa, yoki hujjat shunchaki maqola/darslikning bir qismi bo'lib uning aniq \"uslubiy qo'llanma\" yoki \"yangi laboratoriya\" ekanligi shubha tug'dirsa (administrator ko'rib chiqishi zarur bo'lsa): \"checking\" statusini bering .
                            -Agar taqdim etilgan hujjatlarning ushbu mezonga umuman aloqasi bo'lmasa: \"cancelled\" statusini bering.
                        Javobni hech qanday markdown belgilarisiz (```json...``` kabi emas) va qo'shimcha so'zlarsiz, faqatgina quyidagi qat'iy JSON formatida qaytaring:
                        {\"status\": \"accepted|checking|cancelled\", \"point\": <faqat raqam, masalan 1 yoki 2 >, \"reason\": \"<Qabul qilingan qarorning sababi, aniq qaysi shartlar bajarilganligi yoki nima sababdan rad/tekshiruvga yuborilganligi>\"}",
                    ],
                    [
                        'name' => [
                            'uz' => 'Professor-o‘qituvchining bakalavriat ta’lim yo‘nalishlariga abituriyentlarni (qabul komissiyasiga) jalb etishda targ‘ibot va tashviqot jarayonlarida faol ishtirok etganligi jalb etilishi hamda bakalavr va magistrlarining bandligi va ishga joylashtirilishida ishtiroki',
                            'kaa' => 'Professor-oqıtıwshınıń bakalavriat tálim baǵdarlarına abiturientlerdi (qabıllaw komissiyasına) tartıwda úgit-násiyatlaw hám úgit-násiyatlaw processlerinde belsene qatnasqanlıǵı jáne bakalavr hám magistrlerdiń bántligi hám jumısqa jaylastırılıwında qatnasqanlıǵı;',
                            'ru' => 'Активное участие профессора-преподавателя в процессах пропаганды и агитации при привлечении абитуриентов (в приемную комиссию) на направления образования бакалавриата, а также участие в трудоустройстве и трудоустройстве бакалавров и магистров',
                            'en' => 'Active participation of the professor-teacher in the advocacy and propaganda processes for attracting applicants (to the admissions committee) to undergraduate educational programs, as well as their involvement in the employment and placement of bachelors and masters',
                        ],
                        'desc' => [
                            'uz' => 'Bakalavriat ta’lim yo‘nalishlariga abituriyentlarni qabul jarayoniga jalb etish - 1 ball<br>Bakalavriat talabalarining bandligi va ishga joylashtirishdagi ishtiroki - 1 ball<br>Magistrantlarning bandligi va ishga joylashtirishdagi ishtiroki - 1 ball',
                            'kaa' => 'Bakalavriat tálim baǵdarlarına abiturientlerdi qabıllaw procesine tartıw - 1 ball<br>Bakalavriat studentleriniń bántligi hám jumısqa jaylastırıwda qatnasıwı - 1 ball<br>Magistrantlardıń bántligi hám jumısqa jaylastırıwda qatnasıwı - 1 ball',
                            'ru' => 'Вовлечение абитуриентов в процесс приема на направления образования бакалавриата - 1 балл<br>Участие студентов бакалавриата в трудоустройстве и трудоустройстве - 1 балл<br>Участие магистрантов в трудоустройстве и трудоустройстве - 1 балл',
                            'en' => 'Involvement of applicants in the admission process for undergraduate programs - 1 point<br>Participation of undergraduate students in employment and placement - 1 point<br>Participation of master’s students in employment and placement - 1 point',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'ai',
                        'template' => '0',
                        'res_type' => 'all',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 3,
                            'no_degrees' => 3,
                            'foreign_lang' => 3,
                            'physical' => 3,
                        ],
                        'year' => 2025,
                        'formula_id' => 1,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => "Siz qat'iy AI baholovchisiz. Taqdim etilgan hujjatlarni (buyruqlar, hisobotlar yoki ma'lumotnomalar) tahlil qilib, professor-o'qituvchining quyidagi uchta yo'nalishdagi ishtirokini tasdiqlang.
                        Baholash qoidalari jami %pointing% ballgacha:
                        1. Bakalavriat ta'lim yo'nalishlariga abituriyentlarni qabul jarayoniga (targ'ibot/tashviqot) jalb etganligi tasdiqlansa: +1 ball.
                        2. Bakalavr talabalarining bandligi va ishga joylashtirish jarayonlarida ishtiroki tasdiqlansa: +1 ball.
                        3. Magistrantlarning bandligi va ishga joylashtirish jarayonlarida ishtiroki tasdiqlansa: +1 ball.

                        Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
                        - Agar taqdim etilgan hujjatlarda yuqoridagi shartlarning kamida bittasi aniq va tushunarli tasdiqlansa: \"accepted\" statusini bering. \"point\" qismiga tasdiqlangan holatlar yig'indisini (1, 2 yoki 3 ball) yozing.
                        - Agar hujjatlar xira tushgan bo'lsa, o'qish qiyin bo'lsa yoki ishtiroki bor-yo'qligi bahsli bo'lib, inson (administrator) ko'rib chiqishi talab etilsa: \"checking\" statusini bering.
                        - Agar taqdim etilgan hujjatlarning ushbu mezonga umuman aloqasi bo'lmasa, yoki birorta ham faoliyat tasdiqlanmasa: \"cancelled\" statusini bering.

                        Javobni hech qanday markdown belgilarisiz (```json...``` kabi emas) va qo'shimcha so'zlarsiz, faqatgina quyidagi qat'iy JSON formatida qaytaring:
                        {\"status\": \"accepted|checking|cancelled\", \"point\": <faqat raqam, masalan 2>, \"reason\": \"<Qabul qilingan qarorning sababi, aniq qaysi shartlar bajarilganligi yoki nima sababdan rad/tekshiruvga yuborilganligi>\"}",
                    ],
                    [
                        'name' => [
                            'uz' => 'Professional ta’lim, akademik litsey, kasb - hunar va o‘rta ta’lim maktablarida Master - klass darslarini o‘tish',
                            'kaa' => 'Professional bilimlendiriw, akademiyalıq licey, kásip - óner hám orta bilim beriw mekteplerinde Master - klass sabaqların ótkeriw',
                            'ru' => 'Проведение мастер - классов в профессиональных образовательных учреждениях, академических лицеях, профессиональных и средних образовательных школах',
                            'en' => 'Conducting master classes in vocational schools, academic lyceums, vocational and secondary educational institutions',
                        ],
                        'desc' => [
                            'uz' => 'Belgilangan tartib va talablar asosida o‘tkazilgan, rejalashtirilgan, tasdiqlovchi hujjatlar, dars tahlillari, videofayllar orqali aniqlanadi',
                            'kaa' => 'Belgilengen tártip hám talaplar tiykarında ótkerilgen, rejelestirilgen, tastıyıqlawshı hújjetler, sabaq tallawları, video fayllar arqalı anıqlanadı . ',
                            'ru' => 'Определяется посредством плановых, подтверждающих документов, анализа уроков и видеофайлов, проведенных в соответствии с установленным порядком и требованиями . ',
                            'en' => 'It is determined through planned, supporting documents, lesson analyses, and video files conducted in accordance with established procedures and requirements . ',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'ai',
                        'template' => '0',
                        'res_type' => 'all',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 2,
                            'no_degrees' => 2,
                            'foreign_lang' => 3,
                            'physical' => 3,
                        ],
                        'year' => 2025,
                        'formula_id' => 1,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => "Siz qat'iy AI baholovchisiz. Taqdim etilgan hujjatlarni (dars ishlanmasi, tahlil qog'ozlari, tasdiqlovchi xatlar) yoki videofayllar/havolalarni tahlil qilib, professor-o'qituvchining Master-klass darsi o'tganligini baholang.
                        Baholash qoidalari jami %pointing% ballgacha:
                        1. Mashg'ulot aynan Professional ta'lim muassasasi, akademik litsey, kasb-hunar maktabi yoki o'rta ta'lim maktabida o'tkazilganligi aniq bo'lishi kerak.
                        2. Tasdiqlovchi hujjatda (muhr yoki imzo bilan) yoki videoda ushbu jarayon (Master-klass) o'tkazilganligi aniq aks etgan bo'lishi shart.
                        3. Mezon bajarilganligi tasdiqlansa, unga 1 ball beriladi.

                        Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
                        - Agar taqdim etilgan dalillarda professorning maktab/litseyda master-klass o'tganligi aniq va shubhasiz tasdiqlansa: \"accepted\" statusini bering va \"point\" qismiga 1 yozing.
                        - Agar hujjatlar xira tushgan bo'lsa, videodagi jarayon qayerda bo'layotganini aniqlab bo'lmasa, yoki master-klass ekanligi shubha tug'dirib, administrator ko'rib chiqishi zarur bo'lsa: \"checking\" statusini bering.
                        - Agar taqdim etilgan dalillarning ushbu mezonga umuman aloqasi bo'lmasa (masalan, oddiy OTM darsi, universitet ichidagi yig'ilish) yoki umuman tasdiqlanmasa: \"cancelled\" statusini bering.

                        Javobni hech qanday markdown belgilarisiz (```json...``` kabi emas) va qo'shimcha so'zlarsiz, faqatgina quyidagi qat'iy JSON formatida qaytaring:
                        {\"status\": \"accepted|checking|cancelled\", \"point\": <faqat raqam, masalan 1 yoki 0>, \"reason\": \"<Qabul qilingan qarorning sababi, aniq qaysi shartlar bajarilganligi yoki nima sababdan rad/tekshiruvga yuborilganligi>\"}"
                    ],
                ],
            ],
            [
                'main' => [
                    'name' => [
                        'uz' => 'Xalqaro faoliyat hamda qayta tayyorlash va malaka oshirish va xalqaro darajadagi sertifikatlar',
                        'kaa' => 'Xalíq aralíq jumís hám qayta tayarlaw hám qánigelikti arttíríw hám xalíq aralíq dárejedegi sertifikatlar',
                        'ru' => 'Международная деятельность, переподготовка и повышение квалификации, а также сертификаты международного уровня',
                        'en' => 'International activities, retraining and professional development, and international certificates',
                    ],
                    'report_id' => 1, 'upload' => '1', 'status' => '1',
                ],
                'children' => [
                    [
                        'name' => [
                            'uz' => 'Sohalar bo‘yicha to‘garaklar, xorijiy tilarni o‘rgatish kurslarni tashkil etish',
                            'kaa' => 'Tarawlar boyınsha dógerekler, shet tillerin úyretiw kursların shólkemlestiriw',
                            'ru' => 'Организация кружков по отраслям, курсов по обучению иностранным языкам',
                            'en' => 'Organization of specialized clubs and foreign language courses',
                        ],
                        'desc' => [
                            'uz' => 'To‘garak va kurslarni tashkil qilganligi to‘g‘risida buyruq, to‘garak va kurs rejasi',
                            'kaa' => 'Dógerek hám kurslardı shólkemlestirgenligi haqqında buyrıq, dógerek hám kurs rejesi . ',
                            'ru' => 'Приказ об организации кружков и курсов, план кружка и курса',
                            'en' => 'Order on the organization of clubs and courses, club and course plan',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'ai',
                        'template' => '0',
                        'res_type' => 'all',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 1,
                            'no_degrees' => 2,
                            'foreign_lang' => 2,
                            'physical' => 2,
                        ],
                        'year' => 2025,
                        'formula_id' => 1,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => "Siz qat'iy AI baholovchisiz. Taqdim etilgan hujjatlarni tahlil qilib, professor-o'qituvchi tomonidan sohalar bo'yicha to'garak yoki xorijiy tillarni o'rgatish kursi tashkil etilganligini baholang.
                        Baholash qoidalari jami %pointing% ballgacha:
                        1. Hujjatlar orasida to'garak yoki kurs tashkil qilinganligi to'g'risida rasmiy buyruq (yoki muassasa rahbarining ruxsatnomasi) bo'lishi shart.
                        2. To'garak yoki kursning tasdiqlangan o'quv yoki ish rejasi taqdim etilgan bo'lishi shart.

                        Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
                        - Agar taqdim etilgan hujjatlarda ham rasmiy buyruq, ham to'garak/kurs rejasi mavjud bo'lsa va u professorga tegishli ekanligi aniq bo'lsa: \"accepted\" statusini bering va \"point\" qismiga 1 yozing.
                        - Agar hujjatlar xira tushgan bo'lsa, yoki faqat buyruq bor bo'lib reja yo'q bo'lsa (yoki aksincha), yoxud hujjatning haqiqiyligiga shubha tug'ilib, administrator ko'rib chiqishi zarur bo'lsa: \"checking\" statusini bering (\"point\" ga 0 yozing).
                        - Agar taqdim etilgan hujjatlarning ushbu mezonga umuman aloqasi bo'lmasa (masalan, shunchaki dars jadvali yoki boshqa birovning hujjati): \"cancelled\" statusini bering (\"point\" ga 0 yozing).

                        Javobni hech qanday markdown belgilarisiz (```json...``` kabi emas) va qo'shimcha so'zlarsiz, faqatgina quyidagi qat'iy JSON formatida qaytaring:
                        {\"status\": \"accepted|checking|cancelled\", \"point\": <faqat raqam, masalan 1 yoki 0>, \"reason\": \"<Qabul qilingan qarorning sababi, aniq qaysi shartlar bajarilganligi yoki nima sababdan rad/tekshiruvga yuborilganligi>\"}",
                    ],
                    [
                        'name' => [
                            'uz' => 'Xorijiy tillarda o‘qitiladigan o‘quv kurslarini olib borish',
                            'kaa' => 'Shet tillerinde oqıtılatuǵın oqıw kursların alıp barıw',
                            'ru' => 'Проведение учебных курсов по иностранным языкам',
                            'en' => 'Conducting training courses in foreign languages',
                        ],
                        'desc' => [
                            'uz' => 'Chet tillari fakulteti tarkibidagi kafedralar bundan mustasno',
                            'kaa' => 'Shet tilleri fakulteti quramındaǵı kafedralar buǵan kirmeydi . ',
                            'ru' => 'Исключение составляют кафедры факультета иностранных языков',
                            'en' => 'Except for departments within the Faculty of Foreign Languages',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'ai',
                        'template' => '0',
                        'res_type' => 'all',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 2,
                            'no_degrees' => 2,
                            'physical' => 2,
                        ],
                        'year' => 2025,
                        'formula_id' => 1,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => "Siz qat'iy AI baholovchisiz. Taqdim etilgan hujjatlarni (dars jadvali, o'quv dasturi, kafedra ma'lumotnomasi yoki buyruqlar) tahlil qilib, professor-o'qituvchi o'z mutaxassislik darslarini xorijiy tilda olib borishini tekshiring.
                        Baholash qoidalari jami %pointing% ballgacha:
                        1. Hujjatda dars mashg'ulotlari xorijiy tilda (masalan, ingliz, rus, nemis va h.k.) o'tilishi aniq ko'rsatilgan bo'lishi shart.
                        2. ISTISNO: Ushbu o'qituvchi \"Chet tillari\" fakulteti yoki bevosita chet tillarini o'qitishga ixtisoslashgan kafedra (masalan, Ingliz tili, Nemis tili, Roman-german filologiyasi) o'qituvchisi BO'LMASLIGI shart. Agar o'qituvchi shunday kafedrada ishlasa, ushbu mezon unga qo'llanilmaydi.

                        Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
                        - Agar o'qituvchi nofilologik (chet tili bo'lmagan) kafedrada ishlasa va o'z darslarini xorijiy tilda olib borishi hujjatlarda aniq tasdiqlansa: \"accepted\" statusini bering va \"point\" qismiga 1 yozing.
                        - Agar hujjatlar xira bo'lsa, o'qituvchining qaysi kafedrada ishlashi noaniq bo'lsa, yoki dars xorijiy tilda o'tilishiga shubha tug'ilib, administrator tekshiruvi zarur bo'lsa: \"checking\" statusini bering (\"point\" ga 0 yozing).
                        - Agar o'qituvchi Chet tillari fakulteti/kafedrasida ishlasa, yoki darslari xorijiy tilda o'tilishi umuman tasdiqlanmasa: \"cancelled\" statusini bering (\"point\" ga 0 yozing).

                        Javobni hech qanday markdown belgilarisiz (```json...``` kabi emas) va qo'shimcha so'zlarsiz, faqatgina quyidagi qat'iy JSON formatida qaytaring:
                        {\"status\": \"accepted|checking|cancelled\", \"point\": <faqat raqam, masalan 1 yoki 0>, \"reason\": \"<Qabul qilingan qarorning sababi, istisno holatiga tushgan-tushmaganligi yoki nima sababdan rad/tekshiruvga yuborilganligi>\"}",
                    ],
                    [
                        'name' => [
                            'uz' => 'Xorijiy tillarni bilish darajasi',
                            'kaa' => 'Shet tillerin biliw dárejesi',
                            'ru' => 'Уровень владения иностранными языками',
                            'en' => 'Proficiency in foreign languages',
                        ],
                        'desc' => [
                            'uz' => 'Sertifikatlar(A1 - A2 - 0, 5 ball, B1 - 0,75 ball, B2 - 1 ball, C1 - 1,5 ball, C2 - 2 ball)',
                            'kaa' => 'Sertifikatlar(A1 - A2 - 0, 5 ball, B1 - 0,75 ball, B2 - 1 ball, C1 - 1,5 ball, C2 - 2 ball)',
                            'ru' => 'Сертификаты(A1 - A2 - 0, 5 балла, B1 - 0,75 балла, B2 - 1 балл, C1 - 1,5 балла, C2 - 2 балла)',
                            'en' => 'Certificates(A1 - A2 - 0.5 points, B1 - 0.75 points, B2 - 1 point, C1 - 1.5 points, C2 - 2 points)',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'manual',
                        'template' => '0',
                        'res_type' => 'file',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 2,
                            'no_degrees' => 3,
                            'foreign_lang' => 6,
                            'physical' => 3,
                        ],
                        'year' => 2025,
                        'formula_id' => 2,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => null,
                    ],
                    [
                        'name' => [
                            'uz' => 'Xalqaro loyihalarda ishtiroki',
                            'kaa' => 'Xalıqaralıq joybarlarda qatnasıwı',
                            'ru' => 'Участие в международных проектах',
                            'en' => 'Participation in international projects',
                        ],
                        'desc' => [
                            'uz' => 'Universitet rektori buyrug‘i asosida',
                            'kaa' => 'Universitet rektorı buyrıǵı tiykarında',
                            'ru' => 'На основании приказа ректора университета',
                            'en' => 'Based on the order of the university rector',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'manual',
                        'template' => '0',
                        'res_type' => 'file',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'foreign_lang' => 4,
                            'physical' => 4,
                        ],
                        'year' => 2025,
                        'formula_id' => 1,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => null,
                    ],
                    [
                        'name' => [
                            'uz' => 'Pedagog xodimlarning (xorijda(onlayn, offlayn)) qayta tayyorlash, malaka oshirish va stajirovka kurslaridan o‘tganligi hamda almashinuv dasturlarida ishtirok etganligi',
                            'kaa' => 'Pedagog xızmetkerlerdiń (sırt elde(onlayn, offlayn)) qayta tayarlaw, tájiriybe arttırıw hám stajirovka kurslarınan ótkenligi hám de almasıw baǵdarlamalarında qatnasqanlıǵı;',
                            'ru' => 'Прохождение педагогическими работниками курсов переподготовки, повышения квалификации и стажировки(за рубежом(онлайн, оффлайн)) и участие в программах обмена',
                            'en' => 'The completion of retraining, professional development, and internship courses by teaching staff(abroad(online, offline)) and their participation in exchange programs',
                        ],
                        'desc' => [
                            'uz' => 'QS, THE va ARWU kabi xalqaro tashkilotlari reyting ro‘yxatining nufuzli reytingiga kiruvchi ta’lim muassasalarida malaka oshirish va stajirovka kurslaridan o‘tganligi hamda almashinuv dasturlarida ishtirok etganligi(Top - 100 bo‘lsa - 2 ball, Top - 300 bo‘lsa - 1.5 , Top - 500 bo‘lsa - 1 ball, Top - 1000 bo‘lsa 0,5 ball)',
                            'kaa' => 'QS, THE hám ARWU sıyaqlı xalıqaralıq shólkemlerdiń reyting diziminiń abıraylı reytingine kiretuǵın bilimlendiriw mákemelerinde qánigelik arttırıw hám stajirovka kurslarınan ótkenligi hám de almasıw baǵdarlamalarında qatnasqanlıǵı(Top - 100 bolsa - 2 ball, Top - 300 bolsa - 1.5 , Top - 500 bolsa - 1 ball, Top - 1000 bolsa 0,5 ball)',
                            'ru' => 'Прохождение курсов повышения квалификации и стажировки в образовательных учреждениях, входящих в престижные рейтинги международных организаций, таких как QS, THE и ARWU, а также участие в программах обмена(топ - 100 - 2 балла, топ - 300 - 1,5 балла, топ - 500 - 1 балл, топ - 1000 - 0,5 балла)',
                            'en' => 'Completion of advanced training and internship courses at educational institutions included in the prestigious rankings of international organizations such as QS, THE, and ARWU, as well as participation in exchange programs(Top - 100 - 2 points, Top - 300 - 1.5 points, Top - 500 - 1 point, Top - 1000 - 0.5 points).',
                        ],
                        'observation' => 'last3years',
                        'report_id' => 1,
                        'checking' => 'ai',
                        'template' => '0',
                        'res_type' => 'file',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 2,
                            'no_degrees' => 3,
                            'foreign_lang' => 2,
                            'physical' => 3,
                        ],
                        'year' => 2025,
                        'formula_id' => 2,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => "Siz qat'iy AI baholovchisiz. Taqdim etilgan hujjatni (sertifikat, ma'lumotnoma, xizmat safari buyrug'i yoki diplom) tahlil qilib, professor-o'qituvchining xorijda (onlayn yoki offlayn) malaka oshirish, stajirovka yoki almashinuv dasturida ishtirok etganligini baholang.
                        Baholash qoidalari jami %pointing% ballgacha:
                        1. Hujjatdan xorijiy ta'lim muassasasining nomini aniqlang.
                        2. Ushbu muassasaning QS, THE yoki ARWU xalqaro reytinglaridagi o'rnini (taxminiy) baholang va quyidagi tartibda ball bering:
                           - Top-100 talikka kirsa: 2 ball
                           - Top-300 talikka kirsa: 1.5 ball
                           - Top-500 talikka kirsa: 1 ball
                           - Top-1000 talikka kirsa: 0.5 ball

                        Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
                        - Agar xorijiy muassasada stajirovka/malaka oshirganlik tasdiqlansa va u Top-1000 reytingiga kirishi ma'lum bo'lsa: \"accepted\" statusini bering va \"point\" qismiga mos ballni (2, 1.5, 1 yoki 0.5) yozing.
                        - Agar hujjat xira bo'lsa, xorijiy muassasa nomi to'liq yozilmagan bo'lsa, yoki uning reytingdagi o'rnini aniq belgilash AI uchun qiyin bo'lib, inson (administrator) tekshiruvi talab etilsa: \"checking\" statusini bering (\"point\" ga 0 yozing).
                        - Agar hujjatning ushbu mezonga aloqasi bo'lmasa, soxta bo'lsa yoki muassasa Top-1000 reytingiga kirmasligi aniq bo'lsa: \"cancelled\" statusini bering (\"point\" ga 0 yozing).

                        Javobni hech qanday markdown belgilarisiz (```json...``` kabi emas) va qo'shimcha so'zlarsiz, faqatgina quyidagi qat'iy JSON formatida qaytaring:
                        {\"status\": \"accepted|checking|cancelled\", \"point\": <faqat raqam, masalan 1.5 yoki 0>, \"reason\": \"<Qabul qilingan qarorning sababi, muassasa nomi va uning reytingdagi o'rni haqida qisqacha ma'lumot>\"}",
                    ],
                    [
                        'name' => [
                            'uz' => 'Professor - o‘qituvchilarni xorijiy ((QS, THE, ARWU TOP - 1000 talik) OTMlarida dars mashg‘ulotlarini olib borganligi hamda professor - o‘qituvchilari tomonidan xorijlik olimlar(yekspert, mutaxassis)ni universitetda dars mashg‘ulotlarini olib borishga jalb qilganligi yoki universitetga xorijlik talabalarni jalb qilganligi va universitet bilan halqaro aloqalarni mustahkamlashda qo‘shgan xissasi',
                            'kaa' => 'Professor - oqıtıwshılardıń sırt el (QS, THE, ARWU TOP - 1000 lıq) JOOlarında sabaq ótkenligi hám de professor - oqıtıwshıları tárepinen sırt elli ilimpazlardı(ekspert, qánige) universitette sabaq ótiwge tartqanlıǵı yamasa universitetke sırt elli studentlerdi tartqanlıǵı hám de universitet penen xalıqaralıq baylanıslardı bekkemlewde qosqan úlesi;',
                            'ru' => 'Вклад профессоров и преподавателей в проведение занятий в зарубежных вузах(входящих в ТОП - 1000 QS, THE, ARWU), а также привлечение профессорами и преподавателями зарубежных ученых(экспертов, специалистов) для проведения занятий в университете или привлечение иностранных студентов в университет и укрепление международных связей с университетом',
                            'en' => 'Contribution of professors and teachers to conducting classes in foreign(QS, THE, ARWU TOP - 1000) universities, as well as the involvement of foreign scientists(experts, specialists) by professors and teachers to conduct classes at the university, or the involvement of foreign students in the university, and the strengthening of international relations with the university',
                        ],
                        'desc' => [
                            'uz' => 'QS, THE va ARWU kabi xalqaro tashkilotlari reyting ro‘yxatining nufuzli 1000 taligiga kiruvchi ta’lim muassasalari bilan davlatlararo va oliy ta’lim muassasalari o‘rtasidagi tuzilgan shartnomalar, kelishuvlar, xorijiy oliy ta’lim muassasasidan yuborilgan chaqiruv xatlari hamda OTMning tegishli buyrug‘i asosida baholanadi . (Top - 100 bo‘lsa - 3 ball, Top - 300 bo‘lsa - 2.5, Top - 500 bo‘lsa - 2 ball, Top - 1000 bo‘lsa 1,5 ball, xorijlik talabalarni jalb qilgan bo‘lsa - 3 ball)',
                            'kaa' => 'QS, THE hám ARWU sıyaqlı xalıqaralıq shólkemler reyting diziminiń abıraylı 1000 lıǵına kiriwshi bilimlendiriw mákemeleri menen mámleketler aralıq hám joqarı bilimlendiriw mákemeleri arasında dúzilgen shártnamalar, kelisimler, sırt el joqarı bilimlendiriw mákemesinen jiberilgen shaqırıw xatları hám de JOOnıń tiyisli buyrıǵı tiykarında bahalanadı . (Top - 100 bolsa - 3 ball, Top - 300 bolsa - 2.5, Top - 500 bolsa - 2 ball, Top - 1000 bolsa 1,5 ball, sırt elli studentlerdi tartqan bolsa - 3 ball)',
                            'ru' => 'Оценка проводится на основе договоров, соглашений, заключенных между образовательными учреждениями, входящими в топ - 1000 рейтинга международных организаций, таких как QS, THE и ARWU, и межгосударственными и высшими образовательными учреждениями, пригласительных писем, направленных из зарубежного высшего образовательного учреждения, а также соответствующего приказа вуза . (Топ - 100 - 3 балла, Топ - 300 - 2,5 балла, Топ - 500 - 2 балла, Топ - 1000 - 1,5 балла, привлечение иностранных студентов - 3 балла)',
                            'en' => 'Educational institutions included in the top 1000 of the rating list of international organizations such as QS, THE and ARWU are evaluated on the basis of contracts, agreements concluded between interstate and higher educational institutions, letters of invitation sent from a foreign higher educational institution, as well as the relevant order of the university . (Top - 100 - 3 points, Top - 300 - 2.5 points, Top - 500 - 2 points, Top - 1000 - 1.5 points, 3 points for attracting foreign students) ',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'ai',
                        'template' => '0',
                        'res_type' => 'all',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 3,
                            'no_degrees' => 3,
                            'foreign_lang' => 4,
                            'physical' => 4,
                        ],
                        'year' => 2025,
                        'formula_id' => 2,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => "Siz qat'iy AI baholovchisiz. Taqdim etilgan hujjatlarni (xorijiy OTM bilan shartnomalar, chaqiruv xatlari, dars mashg'ulotlarini o'tkazish haqidagi buyruqlar, xorijlik talabalarni jalb qilishga oid hujjatlar) tahlil qiling.
                        Baholash qoidalari jami %pointing% ballgacha:
                        1. Xorijiy OTMning QS, THE yoki ARWU reytingidagi o'rnini aniqlang (Top-100, 300, 500 yoki 1000).
                        2. Ballar: Top-100 = 3 ball; Top-300 = 2.5 ball; Top-500 = 2 ball; Top-1000 = 1.5 ball.
                        3. Xorijlik talabalarni jalb qilgan bo'lsa: 3 ball.

                        Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
                        - Agar hujjatlar (shartnoma, chaqiruv xati, buyruq) xalqaro aloqani yoki talaba jalb qilinganini aniq tasdiqlasa: \"accepted\" statusini bering va mos ballni \"point\" qismiga yozing.
                        - Agar hujjatlar xira bo'lsa, xorijiy OTMning reytingdagi o'rnini aniqlab bo'lmasa, yoki hujjatlar yetishmovchiligi sababli inson (administrator) tekshiruvi talab etilsa: \"checking\" statusini bering (\"point\": 0).
                        - Agar hujjatlar mezonga aloqasi bo'lmasa, soxta bo'lsa yoki universitet Top-1000 reytingiga kirmasa: \"cancelled\" statusini bering (\"point\": 0).

                        Javobni hech qanday markdown belgilarisiz (```json...``` kabi emas) va qo'shimcha so'zlarsiz, faqatgina quyidagi qat'iy JSON formatida qaytaring:
                        {\"status\": \"accepted|checking|cancelled\", \"point\": <raqam, masalan 3, 2.5, 2, 1.5 yoki 0>, \"reason\": \"<Qabul qilingan qarorning sababi, OTM nomi, uning reyting o'rni va ishtirok darajasi haqida qisqacha izoh>\"}",
                    ],
                ],
            ],
            [
                'main' => [
                    'name' => [
                        'uz' => 'Ilmiy - innovatsion faoliyat',
                        'kaa' => 'Ilimiy - innovaciyalıq jumıs',
                        'ru' => 'Научно - инновационная деятельность',
                        'en' => 'Scientific and innovative activity',
                    ],
                    'report_id' => 1, 'upload' => '1', 'status' => '1',
                ],
                'children' => [
                    [
                        'name' => [
                            'uz' => 'OAK ro‘yxatiga kiritilgan xorijiy va mahalliy  ilmiy jurnallarda («Web of Science», «Scopus»dan tashqari) chop etilgan ilmiy maqolalar',
                            'kaa' => 'JAK dizimine kirgizilgen sırt el hám jergilikli ilimiy jurnallarda ("Web of Science," "Scopus"dan tısqarı) basıp shıǵarılǵan ilimiy maqalalar',
                            'ru' => 'Научные статьи, опубликованные в зарубежных и отечественных научных журналах, включенных в перечень ВАК (кроме "Web of Science," "Scopus")',
                            'en' => 'Scientific articles published in foreign and domestic scientific journals included in the HAC list (except for "Web of Science" and "Scopus") ',
                        ],
                        'desc' => [
                            'uz' => 'Oliy attestatsiya komissiyasi ro‘yxatiga kiritilgan Xorijiy ilmiy jurnallarda («Web of Science», «Scopus»dan tashqari) chop etilgan ilmiy maqolalarga muvofiq baholanadi(xorijiy hamkorlikda chiqarilgan maqolalardan tashqari maqolalalar mualliflariga ball teng taqsimlanadi)',
                            'kaa' => 'Joqarı attestaciya komissiyası dizimine kirgizilgen Sırt el ilimiy jurnallarında ("Web of Science," "Scopus"dan tısqarı) basıp shıǵarılǵan ilimiy maqalalarǵa muwapıq bahalanadı(sırt elli birge islesiwde shıǵarılǵan maqalalardan tısqarı maqalalar avtorlarına ball teń bólistiriledi).',
                            'ru' => 'Оценивается в соответствии с научными статьями, опубликованными в зарубежных научных журналах (кроме "Web of Science," "Scopus"), включенных в список Высшей аттестационной комиссии(авторам статей, кроме статей, опубликованных в зарубежном сотрудничестве, баллы распределяются поровну).',
                            'en' => 'It is evaluated in accordance with scientific articles published in foreign scientific journals (except for "Web of Science" and "Scopus") included in the list of the Higher Attestation Commission(scores are distributed equally among the authors of articles, except for articles published in foreign cooperation).',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'ai',
                        'template' => '3',
                        'res_type' => 'all',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 2,
                            'no_degrees' => 3,
                            'foreign_lang' => 3,
                            'physical' => 3,
                        ],
                        'year' => 2025,
                        'formula_id' => 1,
                        'ai_model' => 'gemini-2.5-pro',
                        'ai_prompt' => "Siz qat'iy AI baholovchisiz. Taqdim etilgan hujjatlarni (ilmiy maqola matni, jurnal muqovasi, mundarija yoki nashr ma'lumotnomasi) tahlil qilib, professor-o'qituvchining maqolasi chop etilganligini baholang.
                        Baholash qoidalari jami %pointing% ballgacha:
                        1. Maqola OAK (Oliy Attestatsiya Komissiyasi) ro'yxatiga kiritilgan xorijiy yoki mahalliy ilmiy jurnalda chop etilganligi tasdiqlanishi kerak.
                        2. Jurnal «Web of Science» yoki «Scopus» bazalariga KIRMASLIGI shart (ular uchun alohida mezon bor).
                        3. Ballni teng taqsimlash uchun maqoladagi barcha mualliflar (hammualliflar) sonini aniqlang.

                        Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
                        - Agar maqola OAK jurnalida chiqqanligi tasdiqlansa va u Scopus/WoS bo'lmasa: \"accepted\" statusini bering. \"point\" qismiga 1 yozing va \"author_count\" qismiga mualliflar sonini kiriting.
                        - Agar hujjat xira bo'lsa, jurnal nomi yoki mualliflar ro'yxati to'liq ko'rinmasa, yoki jurnalning OAK ro'yxatida borligiga ishonch komil bo'lmasa (administrator tekshiruvi talab etilsa): \"checking\" statusini bering (\"point\": 0).
                        - Agar hujjat maqola bo'lmasa, yoxud jurnal «Web of Science» yoki «Scopus» bazasida ekanligi aniq bo'lsa: \"cancelled\" statusini bering (\"point\": 0).

                        Javobni hech qanday markdown belgilarisiz (```json...``` kabi emas) va qo'shimcha so'zlarsiz, faqatgina quyidagi qat'iy JSON formatida qaytaring:
                        {\"status\": \"accepted|checking|cancelled\", \"point\": <raqam, odatda 1 yoki 0>, \"author_count\": <mualliflar soni>, \"reason\": \"<Qabul qilingan qarorning sababi, jurnal nomi, nega aynan shu status berilganligi va mualliflar soni haqida qisqacha izoh>\"}",
                    ],
                    [
                        'name' => [
                            'uz' => 'Impakt - faktor jamlanmasiga kiruvchi jurnallarda chop etilgan maqolalar («Web of Science», «Scopus»dan tashqari) chop etilgan ilmiy maqolalar',
                            'kaa' => 'Impakt - faktor toplamına kiriwshi jurnallarda basıp shıǵarılǵan maqalalar ("Web of Science," "Scopus"dan tısqarı) basıp shıǵarılǵan ilimiy maqalalar',
                            'ru' => 'Статьи, опубликованные в журналах, входящих в совокупность импакт - факторов (кроме "Web of Science," "Scopus") научные статьи, опубликованные',
                            'en' => 'Articles published in journals included in the impact factor collection (except for "Web of Science" and "Scopus") published scientific articles',
                        ],
                        'desc' => [
                            'uz' => 'Impakt - faktor toplamına kiriwshi jurnallarda basıp shıǵarılǵan maqalalarda ("Web of Science," "Scopus"dan tısqarı) basıp shıǵarılǵan ilimiy maqalalarǵa muwapıq bahalanadı(shet elli birge islesiwde shıǵarılǵan maqalalardan tısqarı maqalalar avtorlarına ball teń bólistiriledi).',
                            'kaa' => 'Impakt - faktor toplamına kiriwshi jurnallarda basıp shıǵarılǵan maqalalarda ("Web of Science," "Scopus"dan tısqarı) basıp shıǵarılǵan ilimiy maqalalarǵa muwapıq bahalanadı(shet elli birge islesiwde shıǵarılǵan maqalalardan tısqarı maqalalar avtorlarına ball teń bólistiriledi).',
                            'ru' => 'Оценивается в соответствии с научными статьями, опубликованными в журналах, входящих в совокупность импакт - факторов (кроме "Web of Science," "Scopus") (за исключением статей, опубликованных в зарубежном сотрудничестве, баллы распределяются поровну между авторами статей).',
                            'en' => 'It is evaluated in accordance with scientific articles published in journals included in the impact factor collection (except for "Web of Science" and "Scopus") (scores are distributed equally among the authors of articles, with the exception of articles published in foreign cooperation).',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'ai',
                        'template' => '3',
                        'res_type' => 'all',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 2,
                            'no_degrees' => 3,
                            'foreign_lang' => 3,
                            'physical' => 3,
                        ],
                        'year' => 2025,
                        'formula_id' => 1,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => "Siz qat'iy AI baholovchisiz. Taqdim etilgan hujjatlarni (ilmiy maqola matni, jurnal muqovasi, mundarija yoki impakt-faktorni tasdiqlovchi sertifikat/ma'lumotnoma) tahlil qilib, maqola holatini baholang.
                        Baholash qoidalari jami %pointing% ballgacha:
                        1. Jurnalning aniq \"Impakt-faktor\"ga (Impact Factor) ega ekanligi tasdiqlanishi kerak.
                        2. Jurnal «Web of Science» yoki «Scopus» bazalariga KIRMASLIGI shart. Agar ushbu bazalarda bo'lsa, mezon talabiga tushmaydi.
                        3. Ballni mualliflar o'rtasida teng taqsimlash uchun maqoladagi barcha mualliflar (hammualliflar) sonini aniq hisoblang.
                        Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
                        - Agar jurnal impakt-faktorga ega ekanligi tasdiqlansa va u Scopus/WoS bazasida bo'lmasa: \"accepted\" statusini bering. \"point\" qismiga 1 yozing va \"author_count\" qismiga mualliflar sonini kiriting.
                        - Agar hujjat xira bo'lsa, jurnalning impakt-faktori borligi aniq tasdiqlanmasa, qaysi bazada ekanligiga shubha tug'ilib, inson (administrator) tekshiruvi talab etilsa: \"checking\" statusini bering (\"point\": 0).
                        - Agar hujjat maqola bo'lmasa, jurnalning impakt-faktori yo'qligi aniq bo'lsa, yoki u «Web of Science» / «Scopus» bazalaridan biriga kirishi aniq bo'lsa: \"cancelled\" statusini bering (\"point\": 0).
                        Javobni hech qanday markdown belgilarisiz (```json...``` kabi emas) va qo'shimcha so'zlarsiz, faqatgina quyidagi qat'iy JSON formatida qaytaring:
                        {\"status\": \"accepted|checking|cancelled\", \"point\": <raqam, masalan 1 yoki 0>, \"author_count\": <mualliflar soni raqam ko'rinishida>, \"reason\": \"<Qabul qilingan qarorning sababi, jurnal nomi, impakt-faktori va mualliflar soni haqida qisqacha izoh>\"}",
                    ],
                    [
                        'name' => [
                            'uz' => '“SCOPUS” xalqaro ilmiy - texnik ma’lumotlar bazalaridagi Q1 - Q4 kvartildagi jurnallarda nashr etilgan maqolalar',
                            'kaa' => '"SCOPUS" xalıqaralıq ilimiy - texnikalıq maǵlıwmatlar bazalarındaǵı Q1 - Q4 kvartilindegi jurnallarda járiyalanǵan maqalalar',
                            'ru' => 'Статьи, опубликованные в журналах квартилей Q1 - Q4 международных научно - технических баз данных "SCOPUS"',
                            'en' => 'Articles published in Q1 - Q4 quartile journals in the international scientific and technical databases "SCOPUS"',
                        ],
                        'desc' => [
                            'uz' => 'Scopus va Web of Science bazasi orqali baholanadi (xorijiy hamkorlikda chiqarilgan maqolalardan tashqarilari mualliflariga ball teng taqsimlanadi). Q1, Q2 - bo‘lsa - 100 %, Q3 - Q4 % bo‘lsa - 80 %, konferensiyalarda nashr etilgan maqolalar - 50 % ',
                            'kaa' => 'Scopus hám Web of Science bazası arqalı bahalanadı (shet elli birge islesiwde shıǵarılǵan maqalalardan tısqarı avtorlarına ball teń bólistiriledi). Q1, Q2 bolsa - 100 %, Q3 - Q4 % bolsa - 80 %, konferenciyalarda járiyalanǵan maqalalar - 50 %.',
                            'ru' => 'Оценивается через базы данных Scopus и Web of Science (за исключением статей, опубликованных в зарубежном сотрудничестве, баллы распределяются поровну между авторами). Q1, Q2 - 100 %, Q3 - Q4 % -80 %, статьи, опубликованные на конференциях - 50 % ',
                            'en' => 'It is evaluated through the Scopus and Web of Science databases(except for articles published in foreign cooperation, the authors are awarded equal points). Q1, Q2 - 100 %, Q3 - Q4 % -80 %, articles published at conferences - 50 % ',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'ai',
                        'template' => '3',
                        'res_type' => 'all',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 5,
                            'no_degrees' => 5,
                            'foreign_lang' => 5,
                            'physical' => 5,
                        ],
                        'year' => 2025,
                        'formula_id' => 3,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => "Siz qat'iy AI baholovchisiz. Taqdim etilgan hujjatlarni (ilmiy maqola matni, Scopus/WoS bazasidan skrinshot, sertifikat yoki jurnal muqovasi) tahlil qilib, maqola holatini baholang.
                        Baholash qoidalari jami %pointing% ballgacha:
                        1. Maqola aynan «Scopus» yoki «Web of Science» xalqaro bazalarida indekslangan bo'lishi shart.
                        2. Jurnalning kvartilini (Q1, Q2, Q3, Q4) yoki maqola konferensiya materiali ekanligini aniqlang va shunga mos ball bering:
                           - Q1 yoki Q2 kvartil jurnallar uchun: 1.0 ball (100%)
                           - Q3 yoki Q4 kvartil jurnallar uchun: 0.8 ball (80%)
                           - Konferensiyalarda nashr etilgan maqolalar uchun: 0.5 ball (50%)
                        3. Ballni mualliflar o'rtasida teng taqsimlash uchun maqoladagi barcha mualliflar (hammualliflar) sonini aniq hisoblang.
                        Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
                        - Agar maqola Scopus/WoS bazasida ekanligi va uning kvartili/turi tasdiqlansa: \"accepted\" statusini bering. \"point\" qismiga mos ballni (1.0, 0.8 yoki 0.5) yozing. \"author_count\" qismiga mualliflar sonini kiriting.
                        - Agar hujjat xira bo'lsa, jurnalning Scopus/WoS dagi holati yoki kvartili aniq ko'rsatilmagan bo'lib, inson (administrator) tekshiruvi talab etilsa: \"checking\" statusini bering (\"point\": 0).
                        - Agar hujjatning maqolaga aloqasi bo'lmasa yoki jurnal Scopus/WoS bazalariga umuman kirmasligi aniq bo'lsa: \"cancelled\" statusini bering (\"point\": 0).
                        Javobni hech qanday markdown belgilarisiz (```json...``` kabi emas) va qo'shimcha so'zlarsiz, faqatgina quyidagi qat'iy JSON formatida qaytaring:
                        {\"status\": \"accepted|checking|cancelled\", \"point\": <raqam: 1.0, 0.8, 0.5 yoki 0>, \"author_count\": <mualliflar soni raqamda>, \"reason\": \"<Qabul qilingan qarorning sababi, kvartil darajasi va mualliflar soni haqida qisqacha izoh>\"}",
                    ],
                    [
                        'name' => [
                            'uz' => '«Scopus», «Web of Science»,  «Research Gate» xalqaro ilmiy - texnik bazalaridagi xirsh indeksi',
                            'kaa' => '"Scopus," "Web of Science," "Research Gate" xalıqaralıq ilimiy - texnikalıq bazalarındaǵı xirsh indeksi',
                            'ru' => 'Индекс хирша в международных научно - технических базах "Scopus," "Web of Science," "Research Gate"',
                            'en' => 'Hirsch index in international scientific and technical databases "Scopus," "Web of Science," "Research Gate"',
                        ],
                        'desc' => [
                            'uz' => '«Scopus» h≥5 bo‘lsa 100 %, h = 4 bo‘lsa 75 %, h = 3 bo‘lsa 50 %, h≤2 bo‘lsa 25 %<br>«Web of Science» h≥5 bo‘lsa 100 %, h = 4 bo‘lsa 75 %, h = 3 bo‘lsa 50 %, h≤2 bo‘lsa 25 %<br>«Research Gate» h≥5 bo‘lsa 100 %, h = 4 bo‘lsa 75 %, h = 3 bo‘lsa 50 %, h≤2 bo‘lsa 25 % ',
                            'kaa' => '"Scopus" h≥5 bolsa 100 %, h = 4 bolsa 75 %, h = 3 bolsa 50 %, h≤2 bolsa 25 %<br>"Web of Science" h≥5 bolsa 100 %, h = 4 bolsa 75 %, h = 3 bolsa 50 %, h≤2 bolsa 25 %<br>"Research Gate" h≥5 bolsa 100 %, h = 4 bolsa 75 %, h = 3 bolsa 50 %, h≤2 bolsa 25 % ',
                            'ru' => '"Scopus" 100 % при h≥5, 75 % при h = 4, 50 % при h = 3, 25 % при h≤2 < br>"Web of Science" 100 % при h≥5, 75 % при h = 4, 50 % при h = 3, 25 % при h≤2 < br>"Research Gate" 100 % при h≥5, 75 % при h = 4, 50 % при h = 3, 25 % при h≤2',
                            'en' => '"Scopus" 100 % if h≥5, 75 % if h = 4, 50 % if h = 3, 25 % if h≤2 < br>"Web of Science" 100 % if h≥5, 75 % if h = 4, 50 % if h = 3, 25 % if h≤2 < br>"Research Gate" 100 % if h≥5, 75 % if h = 4, 50 % if h = 3, 25 % if h≤2',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'site:profile:index',
                        'template' => '1',
                        'res_type' => 'all',
                        'upload' => '0', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 3,
                            'no_degrees' => 2,
                            'foreign_lang' => 2,
                            'physical' => 2,
                        ],
                        'year' => 2025,
                        'formula_id' => 2,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => "Siz qat'iy AI baholovchisiz. Taqdim etilgan hujjatni (Scopus, Web of Science yoki Research Gate profili skrinshoti/ma'lumotnomasini) tahlil qilib, professor-o'qituvchining Xirsh indeksini (h-index) baholang.
                        Baholash qoidalari jami %pointing% ballgacha:
                        1. Hujjatdan o'qituvchining \"h-index\" (yoki Hirsch index) qiymatini aniq toping.
                        2. Aniqlangan Xirsh indeksiga ko'ra quyidagi ballarni hisoblang:
                           - h ≥ 5 bo'lsa: 1.0 ball (100%)
                           - h = 4 bo'lsa: 0.75 ball (75%)
                           - h = 3 bo'lsa: 0.5 ball (50%)
                           - h ≤ 2 bo'lsa: 0.25 ball (25%)

                        Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
                        - Agar ilmiy baza nomi va h-index qiymati aniq tasdiqlansa: \"accepted\" statusini bering. \"point\" qismiga mos ballni (1.0, 0.75, 0.5 yoki 0.25) yozing.
                        - Agar hujjat/skrinshot xira bo'lsa, h-index raqami kesilib qolgan bo'lsa, yoki qaysi bazaga tegishliligini aniqlab bo'lmasa (administrator ko'rib chiqishi zarur bo'lsa): \"checking\" statusini bering (\"point\": 0).
                        - Agar hujjatning ushbu ilmiy bazalarga aloqasi bo'lmasa (masalan, shunchaki Google Scholar yuklangan bo'lsa) yoki mutlaqo boshqa narsa bo'lsa: \"cancelled\" statusini bering (\"point\": 0).

                        Javobni hech qanday markdown belgilarisiz (```json...``` kabi emas) va qo'shimcha so'zlarsiz, faqatgina quyidagi qat'iy JSON formatida qaytaring:
                        {\"status\": \"accepted|checking|cancelled\", \"point\": <faqat raqam: 1.0, 0.75, 0.5, 0.25 yoki 0>, \"reason\": \"<Qabul qilingan qarorning sababi, qaysi baza ekanligi va h-index qiymati haqida qisqacha izoh>\"}",
                    ],
                    [
                        'name' => [
                            'uz' => 'Monografiya yozganligi yoki lug‘at tuzganligi',
                            'kaa' => 'Monografiya jazǵanlıǵı yamasa sózlik dúzgenligi',
                            'ru' => 'Написание монографии или составление словаря',
                            'en' => 'Whether he wrote a monograph or compiled a dictionary',
                        ],
                        'desc' => [
                            'uz' => 'Belgilangan talab va tartibda tayyorlanib, universitet kengash qarori bilan ruxsat etilib, chop qilinganligi hamda ISBN  raqami orqali aniqlanadi.',
                            'kaa' => 'Belgilengen talap hám tártipte tayarlanıp, universitet keńesi qararı menen ruqsat etilip, basıp shıǵarılǵanlıǵı hám de ISBN nomeri arqalı anıqlanadı.',
                            'ru' => 'Подготовлено в соответствии с установленными требованиями и порядком, разрешено решением совета университета, опубликовано и определяется по номеру ISBN.',
                            'en' => 'Prepared in accordance with the established requirements and procedure, approved by a decision of the university council, published, and determined by ISBN number.',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'ai',
                        'template' => '0',
                        'res_type' => 'all',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 2,
                            'no_degrees' => 3,
                            'foreign_lang' => 3,
                            'physical' => 3,
                        ],
                        'year' => 2025,
                        'formula_id' => 2,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => "Siz qat'iy AI baholovchisiz. Taqdim etilgan hujjatlarni (monografiya yoki lug'at muqovasi, ISBN sahifasi, Universitet kengashi qarori) tahlil qiling.
                        Baholash qoidalari jami %pointing% ballgacha:
                        1. Hujjatda monografiya yoki lug'atning ISBN raqami aniq ko'rsatilgan bo'lishi shart.
                        2. Hujjatda Universitet kengashining nashr etishga ruxsat beruvchi qarori yoki buyrug'i mavjud bo'lishi shart.
                        3. Mualliflik ulushini aniqlash uchun mualliflar sonini aniqlang.

                        Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
                        - Agar ISBN raqami va Universitet kengash qarori/ruxsati mavjudligi aniq tasdiqlansa: \"accepted\" statusini bering. \"point\" qismiga 1 yozing va \"author_count\" qismiga mualliflar sonini kiriting.
                        - Agar hujjatlar xira bo'lsa, ISBN yoki kengash qarorini o'qib bo'lmasa, yoki resursning mazmuni monografiya/lug'atga mos kelishi shubhali bo'lib, inson (administrator) tekshiruvi talab etilsa: \"checking\" statusini bering (\"point\": 0).
                        - Agar hujjatning ushbu mezonga umuman aloqasi bo'lmasa, yoki ISBN/Kengash qarori umuman topilmasa: \"cancelled\" statusini bering (\"point\": 0).

                        Javobni hech qanday markdown belgilarisiz (```json...``` kabi emas) va qo'shimcha so'zlarsiz, faqatgina quyidagi qat'iy JSON formatida qaytaring:
                        {\"status\": \"accepted|checking|cancelled\", \"point\": <raqam, 1 yoki 0>, \"author_count\": <mualliflar soni raqamda>, \"reason\": \"<Qabul qilingan qarorning sababi, ISBN va kengash qarori borligi yoki yo'qligi haqida izoh>\"}",
                    ],
                    [
                        'name' => [
                            'uz' => 'Ilmiy maslahatchiligida fan doktori (DSC) ilmiy darajali kadr tayyorlagani uchun',
                            'kaa' => 'Ilimiy másláhátshi retinde ilim doktorı (DSC) ilimiy dárejeli kadr tayarlaǵanı ushın',
                            'ru' => 'За подготовку в качестве научного консультанта кадров с ученой степенью доктора наук (DSC)',
                            'en' => 'For training personnel with a Doctor of Science (DSC) degree under the supervision of a scientific consultant',
                        ],
                        'desc' => [
                            'uz' => 'OAK tomonidan taqdim etilgan tegishli hujjat (diplom) asosida baholanadi.',
                            'kaa' => 'JAK tárepinen usınılǵan tiyisli hújjet (diplom) tiykarında bahalanadı.',
                            'ru' => 'Оценивается на основании соответствующего документа (диплома), представленного ВАК.',
                            'en' => 'The assessment is carried out on the basis of a corresponding document (diploma) submitted by the HAC.',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'manual',
                        'template' => '0',
                        'res_type' => 'all',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 3,
                        ],
                        'year' => 2025,
                        'formula_id' => 1,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => "You are an AI evaluator. Verify the provided diploma or HAC (OAK) document proves the professor successfully supervised a Doctor of Science (DSc) candidate. Return a JSON object strictly in this format: {\"status\": true/false, \"reason\": \"explanation\"}.",
                    ],
                    [
                        'name' => [
                            'uz' => 'Ilmiy rahbarligida falsafa doktori (PhD) ilmiy darajali kadr tayyorlagani uchun',
                            'kaa' => 'Ilimiy basshılıǵında filosofiya doktorı (PhD) ilimiy dárejeli kadr tayarlaǵanı ushın',
                            'ru' => 'За подготовку под научным руководством кадров с ученой степенью доктора философии (PhD)',
                            'en' => 'For training personnel with a Doctor of Philosophy (PhD) degree under scientific supervision',
                        ],
                        'desc' => [
                            'uz' => 'OAK tomonidan taqdim etilgan tegishli hujjat (diplom) asosida baholanadi.',
                            'kaa' => 'JAK tárepinen usınılǵan tiyisli hújjet (diplom) tiykarında bahalanadı.',
                            'ru' => 'Оценивается на основании соответствующего документа (диплома), представленного ВАК.',
                            'en' => 'The assessment is carried out on the basis of a corresponding document (diploma) submitted by the HAC.',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'manual',
                        'template' => '0',
                        'res_type' => 'all',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 3,
                            'foreign_lang' => 3,
                        ],
                        'year' => 2025,
                        'formula_id' => 1,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => "You are an AI evaluator. Verify the provided diploma or HAC (OAK) document proves the professor successfully supervised a Doctor of Philosophy (PhD) candidate. Return a JSON object strictly in this format: {\"status\": true/false, \"reason\": \"explanation\"}.",
                    ],
                    [
                        'name' => [
                            'uz' => 'Ilmiy-tadqiqot ishlarining samaradorligi: PATENT',
                            'kaa' => 'Ilimiy-izertlew jumıslarınıń nátiyjeliligi: PATENT',
                            'ru' => 'Эффективность научно-исследовательских работ: ПАТЕНТ',
                            'en' => 'Effectiveness of research work: PATENT',
                        ],
                        'desc' => [
                            'uz' => 'Ixtiro, foydali model, sanoat namunalari va seleksiya yutuqlari uchun olingan patentlar asosida aniqlanadi. Huquq egasi universitet yoki universitet o‘qituvchisi bo‘lishi kerak. Huquq egasi (lari) o‘rtasida teng taqsimlanadi.',
                            'kaa' => 'Oylap tabıw, paydalı model, sanaat úlgileri hám selekciya jetiskenlikleri ushın alınǵan patentler tiykarında anıqlanadı. Huqıq iesi universitet yamasa universitet oqıtıwshısı bolıwı kerek. Huqiq iyesi (leri) arasında teń bólistiriledi.',
                            'ru' => 'На основании полученных патентов на изобретения, полезные модели, промышленные образцы и селекционные достижения. Правообладателем должен быть университет или преподаватель университета. Распределяется поровну между правообладателем (правообладателями).',
                            'en' => 'On the basis of patents for inventions, utility models, industrial designs, and selection achievements. The rights holder must be a university or university lecturer. Shared equally among the rights holder (s).',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'ai',
                        'template' => '0',
                        'res_type' => 'all',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 3,
                            'no_degrees' => 4,
                            'foreign_lang' => 4,
                            'physical' => 4,
                        ],
                        'year' => 2025,
                        'formula_id' => 3,
                        'ai_model' => 'gemini-2.5-pro',
                        'ai_prompt' => "Siz qat'iy AI baholovchisiz. Taqdim etilgan patent hujjatini (ixtiro, foydali model, sanoat namunasi yoki seleksiya yutug'i) tahlil qiling va quyidagi qoidalar asosida baholang:
                        Baholash qoidalari jami %pointing% ballgacha:
                        1. Huquq egasi (patent egasi) sifatida universitet yoki o'qituvchining o'zi ko'rsatilganligini tekshiring. Boshqa tashkilot bo'lsa, rad eting.
                        2. Patentning haqiqiyligi va mavzuni (ixtiro, foydali model va h.k.) aniqlang.
                        3. Asosiy ball = 1. Ballni teng taqsimlash uchun patentda ko'rsatilgan barcha mualliflar sonini aniq hisoblang.
                        Qaror qabul qilish:
                        - \"accepted\": Patent egasi mos bo'lsa va hujjat rasmiy bo'lsa. point = 1, author_count = <mualliflar soni>.
                        - \"checking\": Hujjat xira bo'lsa, huquq egasini aniqlash qiyin bo'lsa yoki shubhali bo'lsa. point = 0, author_count = 0.
                        - \"cancelled\": Patent egasi noto'g'ri bo'lsa yoki hujjat patentga aloqador bo'lmasa. point = 0, author_count = 0.
                        Javobni hech qanday markdown belgilarisiz (```json kabi emas) va qo'shimcha so'zlarsiz, faqatgina quyidagi qat'iy JSON formatida qaytaring:
                        {\"status\": \"accepted|checking|cancelled\", \"point\": <raqam, 1 yoki 0>, \"author_count\": <mualliflar soni raqamda>, \"reason\": \"<Sabab va patent egasi haqida qisqacha izoh>\"}",
                    ],
                    [
                        'name' => [
                            'uz' => 'Ilmiy-tadqiqot ishlarining samaradorligi: MUALLIFLIK GUVOHNOMALARI',
                            'kaa' => 'Ilimiy-izertlew jumıslarınıń nátiyjeliligi: AVTORLÍQ GÚWÁLÍQLARÍ',
                            'ru' => 'Эффективность научно-исследовательских работ: АВТОРСКИЕ УДОСТОВЕРЕНИЯ',
                            'en' => 'Effectiveness of research work: AUTHOR’S CERTIFICATES',
                        ],
                        'desc' => [
                            'uz' => 'Axborot kommunikatsiya texnologiyalariga oid dasturlar va elektron ma’lumotlar bazalari uchun olingan guvohnomalar, mualliflik huquqi bilan himoyalangan turli materiallar orqali aniqlanadi.',
                            'kaa' => 'Málimleme kommunikaciya texnologiyalarına tiyisli baǵdarlamalar hám elektron maǵlıwmatlar bazaları ushın alınǵan gúwalıqlar, avtorlıq huqıq penen qorǵalǵan túrli materiallar arqalı anıqlanadı.',
                            'ru' => 'Информационно-коммуникационные технологии определяются по программам и электронным базам данных, полученным свидетельствам, различным материалам, защищенным авторским правом.',
                            'en' => 'It is determined through certificates obtained for information and communication technology programs and electronic databases, as well as various materials protected by copyright.',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'ai',
                        'template' => '4',
                        'res_type' => 'all',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 1,
                            'no_degrees' => 2,
                            'foreign_lang' => 2,
                            'physical' => 2,
                        ],
                        'year' => 2025,
                        'formula_id' => 1,
                        'ai_model' => 'gemini-2.5-pro',
                        'ai_prompt' => "Siz qat'iy AI baholovchisiz. Taqdim etilgan mualliflik guvohnomasini (dasturiy ta'minot, ma'lumotlar bazasi yoki mualliflik huquqi bilan himoyalangan material) tahlil qiling.
                        Baholash qoidalari jami %pointing% ballgacha:
                        1. Guvohnoma raqami, muallif nomi va obyekt nomi (dastur yoki ma'lumotlar bazasi) aniq ko'rinib turishi shart.
                        2. Baholash usuli: Har bir guvohnoma uchun 1 ball beriladi (mualliflar soniga teng taqsimlanadi).
                        3. Ballni mualliflar o'rtasida teng taqsimlash uchun guvohnomada ko'rsatilgan barcha mualliflar sonini aniq hisoblang.

                        Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
                        - Agar guvohnoma haqiqiy va mezon talablariga mos kelsa: \"accepted\" statusini bering. \"point\" qismiga 1 yozing va \"author_count\" qismiga mualliflar sonini kiriting.
                        - Agar hujjat xira bo'lsa, mualliflar sonini yoki guvohnoma raqamini o'qib bo'lmasa, yoxud hujjat shubhali bo'lib inson (administrator) tekshiruvi talab etilsa: \"checking\" statusini bering (\"point\": 0).
                        - Agar hujjatning mualliflik guvohnomasiga aloqasi bo'lmasa, yoki u soxta bo'lsa: \"cancelled\" statusini bering (\"point\": 0).

                        Javobni hech qanday markdown belgilarisiz (```json...``` kabi emas) va qo'shimcha so'zlarsiz, faqatgina quyidagi qat'iy JSON formatida qaytaring:
                        {\"status\": \"accepted|checking|cancelled\", \"point\": <raqam, 1 yoki 0>, \"author_count\": <mualliflar soni raqamda>, \"reason\": \"<Qabul qilingan qarorning sababi, guvohnoma nomi va mualliflar soni haqida izoh>\"}",
                    ],
                    [
                        'name' => [
                            'uz' => 'Universitet nomidan ilmiy tadbirlardagi ishtirok',
                            'kaa' => 'Universitet atınan ilimiy ilajlarda qatnasıw',
                            'ru' => 'Участие в научных мероприятиях от имени Университета',
                            'en' => 'Participation in scientific events on behalf of the University',
                        ],
                        'desc' => [
                            'uz' => 'Ma’ruza, ko‘rgazma, yarmarka, seminar, tanlovlar yoki konferensiyalarda ma’ruza bilan qatnashishi yoki ushbu ilmiy tadbirlarda sovrinli o‘rinlar uchun',
                            'kaa' => 'Bayanat, kórgizbe, yarmarka, seminar, tańlawlar yamasa konferenciyalarda bayanat penen qatnasıwı yamasa usı ilimiy ilajlarda sıylı orınlar ushın',
                            'ru' => 'За участие с докладом на лекциях, выставках, ярмарках, семинарах, конкурсах или конференциях или за призовые места на этих научных мероприятиях',
                            'en' => 'Participation in lectures, exhibitions, fairs, seminars, competitions, or conferences, or for prize-winning places in these scientific events',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'ai',
                        'template' => '0',
                        'res_type' => 'all',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 2,
                            'no_degrees' => 4,
                            'foreign_lang' => 2,
                            'physical' => 2,
                        ],
                        'year' => 2025,
                        'formula_id' => 1,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => "Siz qat'iy AI baholovchisiz. Taqdim etilgan hujjatlarni (sertifikat, diplom, tashakkurnoma yoki konferensiya dasturi) tahlil qilib, professor-o'qituvchining universitet nomidan ilmiy tadbirlardagi ishtirokini baholang.
                        Baholash qoidalari jami %pointing% ballgacha:
                        1. Xalqaro konferensiya/seminarlarda ma'ruza bilan qatnashganlik: 1.0 ball.
                        2. Respublika miqyosidagi ilmiy tadbirlarda ishtirok: 0.5 ball.
                        3. Tanlov va ko'rgazmalarda sovrinli o'rinlar (1, 2, 3-o'rinlar): 1.5 ball.

                        Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
                        - Agar hujjatda universitet nomidan qatnashganligi va tadbir turi aniq bo'lsa: \"accepted\" statusini bering. \"point\" qismiga mos ballni yozing.
                        - Agar hujjat xira bo'lsa, tadbirning darajasini (xalqaro/respublika) yoki erishilgan o'rinni aniq o'qib bo'lmasa, yoxud hujjat shubhali bo'lib administrator tekshiruvi zarur bo'lsa: \"checking\" statusini bering (\"point\": 0).
                        - Agar hujjatning ilmiy tadbirga aloqasi bo'lmasa (masalan, shunchaki qatnashchi sertifikati yoki notekshiriladigan tadbir): \"cancelled\" statusini bering (\"point\": 0).

                        Javobni hech qanday markdown belgilarisiz (```json...``` kabi emas) va qo'shimcha so'zlarsiz, faqatgina quyidagi qat'iy JSON formatida qaytaring:
                        {\"status\": \"accepted|checking|cancelled\", \"point\": <raqam, 1.0, 0.5, 1.5 yoki 0>, \"reason\": \"<Qabul qilingan qarorning sababi, tadbir turi va nima uchun aynan shu ball berilganligi haqida qisqacha izoh>\"}",
                    ],
                    [
                        'name' => [
                            'uz' => 'Bevosita rahbarligida respublika miqyosdagi yoki xalqaro olimpiadalarida, nufuzli tanlovlarda, (sovrinli o‘rinlarni qo‘lga kiritgan, mukofot (diplom)-larga (stipendiat) sazovor bo‘lgan talabalari uchun',
                            'kaa' => 'Tikkeley basshılıǵında respublikalıq kólemdegi yamasa xalıqaralıq olimpiadalarda, abıraylı tańlawlarda, sıylı orınlardı qolǵa kirgizgen, sıylıq (diplom) larǵa (stipendiat) ie bolǵan studentleri ushın',
                            'ru' => 'Для студентов, занявших призовые места, удостоенных премий (дипломов) (стипендиатов) на республиканских или международных олимпиадах, престижных конкурсах под непосредственным руководством',
                            'en' => 'For students who have won prizes, awards (diplomas), or scholarships at national or international Olympiads and prestigious competitions under their direct supervision',
                        ],
                        'desc' => [
                            'uz' => 'Talabalarga rahbarligi to‘g‘risidagi buyruq yoki kengash qarori, talaba diplomi, sertifikati va boshqa hujjatlari orqali aniqlanadi.',
                            'kaa' => 'Studentlerge basshılıǵı haqqındaǵı buyrıq yamasa keńes qararı, student diplomı, sertifikatı hám basqa hújjetleri arqalı anıqlanadı.',
                            'ru' => 'Определяется приказом или решением совета о руководстве студентами, дипломом, сертификатом и другими документами студента.',
                            'en' => 'It is determined by order or decision of the student council, diploma, certificate and other documents of the student.',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'ai',
                        'template' => '0',
                        'res_type' => 'all',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 3,
                            'no_degrees' => 4,
                            'foreign_lang' => 4,
                            'physical' => 4,
                        ],
                        'year' => 2025,
                        'formula_id' => 2,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => "Siz qat'iy AI baholovchisiz. Taqdim etilgan hujjatlarni (diplom, sertifikat, talabaga rahbarlik buyrug'i) tahlil qilib, professor-o'qituvchining talabalarni nufuzli olimpiada yoki tanlovga tayyorlaganligini baholang.
                        Baholash qoidalari jami %pointing% ballgacha:
                        1. Talabaning sovrinli o'rni yoki stipendiat bo'lganligini tasdiqlovchi hujjat (diplom/sertifikat) bo'lishi shart.
                        2. O'qituvchining talabaga rahbar ekanligini tasdiqlovchi buyruq yoki kengash qarori bo'lishi shart.
                        3. Ballar: Xalqaro sovrin/stipendiya = 2 ball, Respublika miqyosidagi sovrin/stipendiya = 1 ball
                        Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
                        - Agar talabaning sovrinli o'rni VA o'qituvchining rahbarligi hujjatlar bilan tasdiqlansa: \"accepted\" statusini bering. \"point\" qismiga tadbir darajasiga qarab 2 yoki 1 yozing.
                        - Agar hujjatlar xira bo'lsa, o'qituvchi va talaba ism-shariflarini moslashtirish qiyin bo'lsa, yoki tadbir darajasi (xalqaro/respublika) noaniq bo'lib, administrator tekshiruvi zarur bo'lsa: \"checking\" statusini bering (\"point\": 0).
                        - Agar taqdim etilgan hujjatlarning ushbu mezonga umuman aloqasi bo'lmasa (masalan, o'qituvchining shaxsiy yutug'i, talabaning aloqador bo'lmagan sertifikati): \"cancelled\" statusini bering (\"point\": 0).

                        Javobni hech qanday markdown belgilarisiz (```json...``` kabi emas) va qo'shimcha so'zlarsiz, faqatgina quyidagi qat'iy JSON formatida qaytaring:
                        {\"status\": \"accepted|checking|cancelled\", \"point\": <raqam, masalan 2 yoki 1>, \"reason\": \"<Qabul qilingan qarorning sababi, tadbir nomi, darajasi va nima uchun aynan shu ball berilganligi haqida izoh>\"}",
                    ],
                    [
                        'name' => [
                            'uz' => 'Talabalarning to‘garaklariga rahbarlik qilish',
                            'kaa' => 'Studentlerdiń dógereklerine basshılıq etiw',
                            'ru' => 'Руководство студенческими кружками',
                            'en' => 'Leadership of student clubs',
                        ],
                        'desc' => [
                            'uz' => 'To‘garak buyrug‘i va rejasi',
                            'kaa' => 'Dógerek buyrıǵı hám rejesi',
                            'ru' => 'Приказ и план кружка',
                            'en' => 'Order and plan of the circle',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'ai',
                        'template' => '0',
                        'res_type' => 'all',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 3,
                            'no_degrees' => 3,
                            'foreign_lang' => 3,
                            'physical' => 3,
                        ],
                        'year' => 2025,
                        'formula_id' => 2,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => "Siz qat'iy AI baholovchisiz. Taqdim etilgan hujjatlarni (to'garak tashkil etish to'g'risidagi buyruq va to'garakning tasdiqlangan ish rejasi) tahlil qiling.
                        Baholash qoidalari jami %pointing% ballgacha:
                        1. Hujjatlar orasida professor-o'qituvchi nomiga rasmiylashtirilgan to'garak tashkil etish to'g'risidagi buyruq (yoki ruxsatnoma) bo'lishi shart.
                        2. Hujjatlar orasida to'garakning mavzular va muddatlar ko'rsatilgan tasdiqlangan ish rejasi bo'lishi shart.

                        Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
                        - Agar ham rasmiy buyruq, ham tasdiqlangan ish rejasi mavjud bo'lsa: \"accepted\" statusini bering va \"point\" qismiga 1 yozing.
                        - Agar hujjatlar xira bo'lsa, o'qib bo'lmasa, yoki hujjatlarning biri (buyruq yoki reja) yetishmayotgan bo'lsa (administrator ko'rib chiqishi uchun): \"checking\" statusini bering.
                        - Agar hujjatlarning ushbu mezonga umuman aloqasi bo'lmasa yoki soxta bo'lsa: \"cancelled\" statusini bering.

                        Javobni hech qanday markdown belgilarisiz (```json...``` kabi emas) va qo'shimcha so'zlarsiz, faqatgina quyidagi qat'iy JSON formatida qaytaring:
                        {\"status\": \"accepted|checking|cancelled\", \"point\": <raqam: 1 yoki 0>, \"reason\": \"<Qabul qilingan qarorning sababi va hujjatlardagi holat haqida qisqacha izoh>\"}",
                    ],
                    [
                        'name' => [
                            'uz' => 'Sohalar buyurtmalari asosida (xo‘jalik, innovatsion shartnomalari orqali) o‘tkazilgan ilmiy (ilmiy-ijodiy) tadqiqotlari orqali topilgan mablag‘',
                            'kaa' => 'Tarawlardıń buyırtpaları tiykarında (xojalıq, innovaciya shártnamaları arqalı) ótkerilgen ilimiy (ilimiy-dóretiwshilik) izertlewleri arqalı tabılǵan qarjı',
                            'ru' => 'Средства, полученные за счет научных (научно-творческих) исследований, проведенных на основе отраслевых заказов (хозяйственных, инновационных договоров)',
                            'en' => 'Funds earned through scientific (scientific-creative) research conducted on the basis of industry orders (through economic, innovative contracts)',
                        ],
                        'desc' => [
                            'uz' => 'Sohalar buyurtmalari asosida (xo‘jalik, innovatsion shartnomalari orqali) o‘tkazilgan ilmiy (ilmiy-ijodiy) tadqiqotlariga rahbarlik yoki ishtiroki va ishlab topilgan mablag‘lr asosida baholanadi. Mablag‘ universitet hisobiga tushgan bo‘lishi kerak.',
                            'kaa' => 'Tarawlardıń buyırtpaları tiykarında (xojalıq, innovaciyalıq shártnamaları arqalı) ótkerilgen ilimiy (ilimiy-dóretiwshilik) izertlewlerine basshılıq yamasa qatnasıwı hám islep tabılǵan qarjılar tiykarında bahalanadı. Qarjı universitet esabına túsken bolıwı kerek.',
                            'ru' => 'Оценивается на основе руководства или участия в научных (научно-творческих) исследованиях, проводимых по заказам отраслей (через хозяйственные, инновационные договоры), и заработанных средств. Деньги должны были поступить на счет университета.',
                            'en' => 'It is evaluated based on leadership or participation in scientific (scientific-creative) research conducted on the basis of industry orders (through economic, innovative contracts) and earned funds. The funds must have been deposited into the university’s account.',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'ai',
                        'template' => '0',
                        'res_type' => 'all',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 5,
                            'no_degrees' => 4,
                            'foreign_lang' => 4,
                            'physical' => 4,
                        ],
                        'year' => 2025,
                        'formula_id' => 1,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => "Siz qat'iy AI baholovchisiz. Taqdim etilgan shartnomalar, to‘lov hujjatlari va ilmiy-tadqiqot hisobotlarini tahlil qilib, professor-o‘qituvchining xo‘jalik yoki innovatsion shartnomalardagi ishtirokini baholang.
                        Baholash qoidalari jami %pointing% ballgacha:
                        1. Hujjatda shartnoma mavzusi, buyurtmachi (soha) va universitet hisobiga tushgan mablag‘ miqdori aniq ko‘rsatilgan bo‘lishi shart.
                        2. O‘qituvchining ushbu loyihada \"rahbar\" yoki \"asosiy ijrochi\" ekanligi tasdiqlanishi kerak.
                        3. Ballarni mablag‘ hajmi va ishtirok darajasiga qarab administrator belgilaydi, siz esa faqat hujjat haqiqiyligini tekshiring.

                        Tahlil natijasiga ko‘ra quyidagi qarorlardan birini qabul qiling:
                        - Agar shartnoma, to‘lov hujjati (universitet hisobiga tushganini tasdiqlovchi) va o‘qituvchining ishtiroki aniq bo‘lsa: \"accepted\" statusini bering. \"point\" qismiga hisobot asosida 1 ball (yoki administrator belgilaydigan qiymat) yozing.
                        - Agar hujjatlar xira bo‘lsa, mablag‘ miqdorini aniq o‘qib bo‘lmasa, yoki universitet hisobiga tushganligi haqida ma’lumot yetishmasa (administrator tekshiruvi uchun): \"checking\" statusini bering.
                        - Agar shartnoma shaxsiy bo‘lsa (universitetga aloqasi yo‘q), hujjat soxta bo‘lsa yoki umuman moliyaviy hujjat bo‘lmasa: \"cancelled\" statusini bering.

                        Javobni hech qanday markdown belgilarisiz (```json...``` kabi emas) va qo‘shimcha so‘zlarsiz, faqatgina quyidagi qat’iy JSON formatida qaytaring:
                        {\"status\": \"accepted|checking|cancelled\", \"point\": <raqam: 1 yoki 0>, \"reason\": \"<Qabul qilingan qarorning sababi, shartnoma summasi va universitet hisobiga tushganligi haqida qisqacha izoh>\"}",
                    ],
                    [
                        'name' => [
                            'uz' => 'Davlat grantlari asosida ilmiy-tadqiqot loyihalarda ishtiroki',
                            'kaa' => 'Mámleketlik grantlar tiykarında ilimiy-izertlew joybarlarında qatnasıwı.',
                            'ru' => 'Участие в научно-исследовательских проектах на основе государственных грантов',
                            'en' => 'Participation in research projects based on state grants',
                        ],
                        'desc' => [
                            'uz' => 'Davlat grantlari asosida o‘tkazilgan tadqiqotlariga rahbarligi yoki a’zoligi hujjatlar asosida baholanadi. Loyiha universitet doirasida bo‘lishi lozim.  Boshqa OTMlardagi ishtiroki hisobga olinmaydi.',
                            'kaa' => 'Mámleketlik grantlar tiykarında ótkerilgen izertlewlerine basshılıǵı yamasa aǵzalıǵı hújjetler tiykarında bahalanadı. Joybar universitet sheńberinde bolıwı kerek.  Basqa JOOlardaǵı qatnasıwı esapqa alınbaydı.',
                            'ru' => 'Руководство или членство в исследованиях, проводимых на основе государственных грантов, оценивается на основании документов. Проект должен быть в рамках университета.  Участие в других вузах не учитывается.',
                            'en' => 'Leadership or membership in research conducted on the basis of state grants is evaluated on the basis of documents. The project should be within the university.  Participation in other universities is not taken into account.',
                        ],
                        'observation' => 'project_finished',
                        'report_id' => 1,
                        'checking' => 'ai',
                        'template' => '0',
                        'res_type' => 'all',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 4,
                            'no_degrees' => 1,
                            'foreign_lang' => 1,
                            'physical' => 1,
                        ],
                        'year' => 2025,
                        'formula_id' => 1,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => "Siz qat'iy AI baholovchisiz. Taqdim etilgan hujjatlarni (davlat granti shartnomasi, loyihada ishtirok etish to'g'risidagi buyruq yoki ilmiy loyiha hisoboti) tahlil qiling.
                        Baholash qoidalari jami %pointing% ballgacha:
                        1. Loyiha aynan sizning universitet doirasida amalga oshirilayotganligi tasdiqlanishi shart. Boshqa OTMlardagi loyihalar mezon talabiga javob bermaydi.
                        2. O'qituvchining ushbu loyihada \"rahbar\" yoki \"a'zo\" ekanligi aniq bo'lishi kerak.
                        3. Ballar: Loyiha rahbari bo'lsa - 2 ball, loyiha a'zosi bo'lsa - 1 ball.

                        Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
                        - Agar hujjatlar loyihaning universitetga tegishli ekanligini va o'qituvchining ishtirokini tasdiqlasa: \"accepted\" statusini bering. \"point\" qismiga rahbarlik uchun 2, a'zolik uchun 1 yozing.
                        - Agar hujjatlar xira bo'lsa, universitet nomi ko'rinmasa yoki loyiha qaysi OTMga tegishliligi shubhali bo'lib, administrator tekshiruvi zarur bo'lsa: \"checking\" statusini bering (\"point\": 0).
                        - Agar loyiha boshqa OTMga tegishli bo'lsa, soxta bo'lsa yoki umuman grantga aloqasi bo'lmasa: \"cancelled\" statusini bering (\"point\": 0).

                        Javobni hech qanday markdown belgilarisiz (```json...``` kabi emas) va qo'shimcha so'zlarsiz, faqatgina quyidagi qat'iy JSON formatida qaytaring:
                        {\"status\": \"accepted|checking|cancelled\", \"point\": <raqam: 2, 1 yoki 0>, \"reason\": \"<Qabul qilingan qarorning sababi, loyiha nomi, universitet doirasidaligi va ishtirok darajasi haqida izoh>\"}",
                    ],
                    [
                        'name' => [
                            'uz' => 'Ixtisoslashgan ilmiy kengashlarda rais, kotib va a’zo, shuningdek, respublika ilmiy-texnik kengashlarda a’zolik uchun',
                            'kaa' => 'Qánigelestirilgen ilimiy keńeslerde baslıq, xatker hám aǵza, sonday-aq, respublikalıq ilimiy-texnikalıq keńeslerde aǵzalıq ushın',
                            'ru' => 'За председателя, секретаря и члена специализированных научных советов, а также за членство в республиканских научно-технических советах',
                            'en' => 'Chairman, Secretary, and member of specialized scientific councils, as well as for membership in republican scientific and technical councils',
                        ],
                        'desc' => [
                            'uz' => 'OAK rayosati va vazirlik qarori, buyrug‘i asosida aniqlanadi.',
                            'kaa' => 'JAK prezidiumi hám ministrlik qararı, buyrıǵı tiykarında aniqlanadi.',
                            'ru' => 'Определяется на основании решения, приказа президиума ВАК и министерства.',
                            'en' => 'Determined on the basis of a decision or order of the HAC Presidium and the Ministry.',
                        ],
                        'observation' => 'end_of_council',
                        'report_id' => 1,
                        'checking' => 'ai',
                        'template' => '0',
                        'res_type' => 'all',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 2,
                            'foreign_lang' => 2,
                        ],
                        'year' => 2025,
                        'formula_id' => 2,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => "Siz qat'iy AI baholovchisiz. Taqdim etilgan hujjatlarni (Oliy Attestatsiya Komissiyasi - OAK rayosati qarori yoki Vazirlik buyrug'ini) tahlil qiling.
                        Baholash qoidalari jami %pointing% ballgacha:
                        1. Hujjat OAK rayosati yoki Vazirlikning rasmiy qarori, tasdiqlangan ro'yxati yoki buyrug'i ekanligini tekshiring.
                        2. Hujjatda professor-o'qituvchining Ixtisoslashgan ilmiy kengashda yoki Respublika ilmiy-texnik kengashida \"rais\", \"kotib\" yoki \"a'zo\" sifatida kiritilganligini aniqlang.

                        Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
                        - Agar hujjatning OAK yoki vazirlikka tegishli ekanligi va undagi professor-o'qituvchining lavozimi (rais/kotib/a'zo) aniq tasdiqlansa: \"accepted\" statusini bering. \"point\" qismiga 1 yozing.
                            - Agar hujjat o'qib bo'lmaydigan darajada xira bo'lsa, rasmiy muhr/imzolar ko'rinmasa yoki hujjat faqat kafedra/fakultet darajasida bo'lib, kengashning OAK/Vazirlik darajasida ekanligi shubhali bo'lsa (administrator tekshiruvi zarur bo'lsa): \"checking\" statusini bering (\"point\": 0).
                        - Agar taqdim etilgan hujjat shaxsiy tashakkurnoma bo'lsa, OAK yoki vazirlikka aloqasi bo'lmasa yoxud boshqa turdagi yig'ilish bayonnomasi bo'lsa: \"cancelled\" statusini bering (\"point\": 0).

                        Javobni hech qanday markdown belgilarisiz (```json...``` kabi emas) va qo'shimcha so'zlarsiz, faqatgina quyidagi qat'iy JSON formatida qaytaring:
                        {\"status\": \"accepted|checking|cancelled\", \"point\": <raqam, masalan 1 yoki 0>, \"reason\": \"<Qabul qilingan qarorning sababi, qaysi organning qarori ekanligi va kengashdagi qanday lavozim (rais/kotib/a'zo) ekanligi haqida qisqacha izoh>\"}",
                    ],
                ],
            ],
            [
                'main' => [
                    'name' => [
                        'uz' => 'Ijtimoiy-ma’naviy faoliyati, ijro va mehnat intizomi',
                        'kaa' => 'Sociallıq-ruwxıy iskerligi, atqarıw hám miynet tártibi',
                        'ru' => 'Социально-духовная деятельность, исполнительская и трудовая дисциплина',
                        'en' => 'Socio-spiritual activity, performance, and labor discipline',
                    ],
                    'report_id' => 1, 'upload' => '1', 'status' => '1',
                ],
                'children' => [
                    [
                        'name' => [
                            'uz' => 'OAV yoki ijtimoiy tarmoqlarda universitet va mamlakatda amalga oshirilayotgan islohotlar yuzasidan chiqishlar qilganlig',
                            'kaa' => 'ǴXQ yamasa sociallıq tarmaqlarda universitet hám mámlekette ámelge asırılıp atırǵan reformalar boyınsha shıǵıp sóylegenligi',
                            'ru' => 'Выступления в СМИ или социальных сетях по поводу реформ, проводимых в университете и стране',
                            'en' => 'Making appearances in the media or social networks regarding the university and the reforms being implemented in the country',
                        ],
                        'desc' => [
                            'uz' => 'Bajarilgan ishlar bo‘yicha taqdim etilgan hujjatlar, videolavhalar asosida aniqlanadi va baholanadi (respublika, xorijiy OAVlarda).',
                            'kaa' => 'Orınlanǵan jumıslar boyınsha usınılǵan hújjetler, video kórinisler tiykarında anıqlanadı hám bahalanadı (respublika, sırt el ǵalaba xabar qurallarında).',
                            'ru' => 'Определяется и оценивается на основе представленных документов, видеороликов о проделанной работе (в республиканских, зарубежных СМИ).',
                            'en' => 'The results of the work performed are determined and evaluated based on submitted documents and video materials (in national and foreign media).',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'ai',
                        'template' => '0',
                        'res_type' => 'all',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 3,
                            'no_degrees' => 3,
                            'foreign_lang' => 2,
                            'physical' => 3,
                        ],
                        'year' => 2025,
                        'formula_id' => 1,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => "Siz qat'iy AI baholovchisiz. Taqdim etilgan hujjatlarni (OAVdagi maqolalar, video havola, ijtimoiy tarmoqdagi rasmiy postlar yoki efir lavhalari) tahlil qiling.
                        Baholash qoidalari jami %pointing% ballgacha:
                        1. Chiqish mavzusi universitet yoki mamlakatdagi islohotlar (ta'lim, ijtimoiy, iqtisodiy va h.k.) targ'ibotiga oid bo'lishi shart.
                        2. OAV darajasini aniqlang:
                           - Xalqaro OAVlarda chiqish: 2.0 ball.
                           - Respublika darajasidagi OAVlarda (TV, gazeta, rasmiy portal): 1.0 ball.
                           - Ijtimoiy tarmoqlardagi nufuzli sahifalardagi rasmiy chiqishlar: 0.5 ball.

                        Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
                        - Agar chiqish islohotlar targ'ibotiga oidligi va OAV darajasi tasdiqlansa: \"accepted\" statusini bering va \"point\" qismiga mos ballni yozing.
                        - Agar hujjat (video/maqola) xira bo'lsa, chiqish islohotlarga aloqadorligi yoki OAV darajasi aniq bo'lmasa, yoxud inson (administrator) tekshiruvi talab etilsa: \"checking\" statusini bering (\"point\": 0).
                        - Agar chiqish shaxsiy xarakterda bo'lsa, islohotlarga aloqasi bo'lmasa yoki OAV nufuzi past bo'lsa: \"cancelled\" statusini bering (\"point\": 0).

                        Javobni hech qanday markdown belgilarisiz (```json...``` kabi emas) va qo'shimcha so'zlarsiz, faqatgina quyidagi qat'iy JSON formatida qaytaring:
                        {\"status\": \"accepted|checking|cancelled\", \"point\": <raqam: 2.0, 1.0, 0.5 yoki 0>, \"reason\": \"<Qabul qilingan qarorning sababi, qaysi OAV ekanligi va islohotlarning qaysi yo'nalishi yoritilganligi haqida qisqacha izoh>\"}",
                    ],
                    [
                        'name' => [
                            'uz' => 'Davlat hokimiyati va boshqaruvi organlarining murojaatiga asosan ilmiy-amaliy takliflarni tayyorlash va ishtirok etish',
                            'kaa' => 'Mámleketlik hákimiyat hám basqarıw uyımlarınıń múrájatına tiykarlanıp ilimiy-ámeliy usınıslardı tayarlaw hám qatnasıw',
                            'ru' => 'Подготовка и участие в научно-практических предложениях на основе обращений органов государственной власти и управления',
                            'en' => 'Preparation and participation in scientific and practical proposals based on appeals from state authorities and administration bodies',
                        ],
                        'desc' => [
                            'uz' => 'Davlat hokimiyati va boshqaruvi organlarining murojaati asos qilinadi',
                            'kaa' => 'Mámleketlik hákimiyat hám basqarıw uyımlarınıń múrájatı tiykarlanadı.',
                            'ru' => 'Основанием является обращение органов государственной власти и управления.',
                            'en' => 'The basis is the appeal of state authorities and administration bodies.',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'ai',
                        'template' => '0',
                        'res_type' => 'all',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 1,
                            'no_degrees' => 1,
                            'foreign_lang' => 1,
                            'physical' => 2,
                        ],
                        'year' => 2025,
                        'formula_id' => 1,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => "Siz qat'iy AI baholovchisiz. Taqdim etilgan hujjatlarni (Davlat hokimiyati yoki boshqaruv organidan kelgan rasmiy xat, murojaat va professor-o'qituvchi tomonidan tayyorlangan ilmiy-amaliy taklif) tahlil qiling.
                        Baholash qoidalari jami %pointing% ballgacha:
                        1. Hujjatda murojaat qilgan tashkilot Davlat hokimiyati yoki boshqaruv organi (masalan, Vazirliklar, Hokimiyatlar, Agentliklar) ekanligini tekshiring.
                        2. O'qituvchining ushbu murojaatga javoban ilmiy-amaliy taklif tayyorlaganligi yoki mazkur jarayonda ishtiroki tasdiqlanishi shart.
                        3. Baholash: Ushbu mezon uchun belgilangan ball (odatda 1 ball) beriladi.

                        Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
                        - Agar davlat organining rasmiy murojaati va o'qituvchining unga javob sifatida ilmiy-amaliy ishlanmasi/taklifi tasdiqlansa: \"accepted\" statusini bering. \"point\" qismiga 1 yozing.
                        - Agar hujjatlar xira bo'lsa, tashkilot nomi yoki murojaat mazmunini o'qib bo'lmasa, yoxud hujjat shubhali bo'lib administrator tekshiruvi zarur bo'lsa: \"checking\" statusini bering (\"point\": 0).
                        - Agar hujjat davlat organi emas, balki xususiy firma tomonidan yuborilgan bo'lsa, yoki umuman murojaatga aloqasi bo'lmasa: \"cancelled\" statusini bering (\"point\": 0).

                        Javobni hech qanday markdown belgilarisiz (```json...``` kabi emas) va qo'shimcha so'zlarsiz, faqatgina quyidagi qat'iy JSON formatida qaytaring:
                        {\"status\": \"accepted|checking|cancelled\", \"point\": <raqam, 1 yoki 0>, \"reason\": \"<Qabul qilingan qarorning sababi, murojaat qilgan davlat organi nomi va taklifning mazmuni haqida qisqacha izoh>\"}",
                    ],
                    [
                        'name' => [
                            'uz' => 'Bevosita o‘zining tashabbusi bilan ma’naviy-ma’rifiy, adabiy uchrashuvlar, intel-lektual o‘yinlar, mushoira kechalari, turli tanlovlar tashkil etganligi',
                            'kaa' => 'Tikkeley óziniń baslaması menen ruwxıy-aǵartıwshılıq, ádebiy ushırasıwlar, intellektual oyınlar, mushayra kesheleri, túrli tańlawlar shólkemlestirgenligi.',
                            'ru' => 'Организация по собственной инициативе духовно-просветительских, литературных встреч, интеллектуальных игр, вечеров поэзии, различных конкурсов;',
                            'en' => 'Organizing spiritual-educational and literary meetings, intellectual games, poetry evenings, and various competitions on his own initiative;',
                        ],
                        'desc' => [
                            'uz' => 'Ma’naviy-ma’rifiy, adabiy uchrashuvlar (bayonlari), intellektual o‘yinlar, mushoira kechalari, turli tanlovlar tashkil (e’lon) etganligi',
                            'kaa' => 'Ruwxıy-aǵartıwshılıq, ádebiy ushırasıwlar (bayanatları), intellektual oyınlar, mushayra kesheleri, túrli tańlawlar shólkemlestirilgenligi (járiyalanǵanlıǵı)',
                            'ru' => 'Организация (объявление) духовно-просветительских, литературных встреч, интеллектуальных игр, поэтических вечеров, различных конкурсов',
                            'en' => 'Organization (announcement) of spiritual, educational, and literary meetings (expressions), intellectual games, poetry evenings, and various competitions',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'ai',
                        'template' => '0',
                        'res_type' => 'all',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 2,
                            'no_degrees' => 3,
                            'foreign_lang' => 1,
                            'physical' => 3,
                        ],
                        'year' => 2025,
                        'formula_id' => 1,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => "Siz qat'iy AI baholovchisiz. Taqdim etilgan hujjatlarni (tashabbuskorlik bo'yicha buyruq/ruxsatnoma, tadbir bayonnomasi, e'lonlar, fotosuratlar yoki tadbir hisoboti) tahlil qiling.
                        Baholash qoidalari jami %pointing% ballgacha:
                        1. Hujjatda o'qituvchining tashabbusi bilan ma'naviy-ma'rifiy tadbir (uchrashuv, mushoira, intellektual o'yin yoki tanlov) tashkil etilganligi tasdiqlanishi shart.
                        2. Hujjatlarda tadbirning o'tgan sanasi, o'tkazilish joyi va mavzusi aniq aks etishi kerak.
                        3. Baholash: Har bir tashkil etilgan tadbir uchun 0.5 ball beriladi.

                        Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
                        - Agar tadbir tashkil etilganligi va o'qituvchining tashabbuskorligi hujjatlar (bayonnoma, buyruq, e'lon) bilan tasdiqlansa: \"accepted\" statusini bering va hisobot asosida mos ballni (0.5 ball) yozing.
                        - Agar hujjatlar xira bo'lsa, tadbir nomi yoki o'qituvchining tashabbuskorligi aniq bo'lmasa, yoxud inson (administrator) tekshiruvi talab etilsa: \"checking\" statusini bering (\"point\": 0).
                        - Agar taqdim etilgan hujjatlar oddiy ishtirokchi sertifikati bo'lsa (o'zi tashkil etmagan bo'lsa) yoki hujjatlar soxta bo'lsa: \"cancelled\" statusini bering (\"point\": 0).

                        Javobni hech qanday markdown belgilarisiz (```json...``` kabi emas) va qo'shimcha so'zlarsiz, faqatgina quyidagi qat'iy JSON formatida qaytaring:
                        {\"status\": \"accepted|checking|cancelled\", \"point\": <raqam, masalan 0.5 yoki 0>, \"reason\": \"<Qabul qilingan qarorning sababi, tadbir nomi va tashabbuskorlik dalili haqida izoh>\"}",
                    ],
                    [
                        'name' => [
                            'uz' => 'Talabalar bilan tarbiyaviy ish bo‘yicha tadbirlarda ishtirok etish',
                            'kaa' => 'Studentler menen tárbiyalıq jumıs boyınsha ilajlarda qatnasıw',
                            'ru' => 'Участие в мероприятиях по воспитательной работе со студентами',
                            'en' => 'Participation in educational work activities with students',
                        ],
                        'desc' => [
                            'uz' => 'Ma’naviy-ma’rifiy ishlar, sport klublari, madaniy tadbirlar va Universitetga biriktirilgan mahalalarda tadbirlar olib borish hamda boshqalar',
                            'kaa' => 'Ruwxıy-aǵartıwshılıq jumıslar, sport klubları, mádeniy ilajlar hám Universitetke biriktirilgen máhállelerde ilajlar ótkeriw hám basqalar',
                            'ru' => 'Проведение духовно-просветительской работы, спортивных клубов, культурных мероприятий и мероприятий в махаллях, закрепленных за Университетом, и др.',
                            'en' => 'Conducting spiritual and educational work, sports clubs, cultural events, and events in the mahallas assigned to the University, etc.',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'ai',
                        'template' => '0',
                        'res_type' => 'all',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 2,
                            'no_degrees' => 3,
                            'foreign_lang' => 2,
                            'physical' => 2,
                        ],
                        'year' => 2025,
                        'formula_id' => 1,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => "Siz qat'iy AI baholovchisiz. Taqdim etilgan hujjatlarni (ma'naviy-ma'rifiy tadbir bayonnomalari, sport musobaqalari hisobotlari, mahalla bilan hamkorlik hujjatlari yoki tadbir fotosuratlari) tahlil qiling.
                        Baholash qoidalari jami %pointing% ballgacha:
                        1. Hujjatda o'qituvchining talabalar bilan birga ma'naviy-ma'rifiy, sport yoki madaniy tadbirlarda (yoki biriktirilgan mahallada) ishtirok etganligi tasdiqlanishi shart.
                        2. Hujjatlarda tadbirning o'tkazilish sanasi, joyi va mazmuni aniq bo'lishi kerak.
                        3. Baholash: Har bir ishtirok etilgan tadbir uchun 0.3 ball beriladi.

                        Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
                        - Agar hujjatlar o'qituvchining talabalar bilan tadbirlarda faol ishtirok etganligini tasdiqlasa: \"accepted\" statusini bering va hisobot asosida ballni (0.3 ball) yozing.
                        - Agar hujjatlar xira bo'lsa, tadbir mazmuni tushunarsiz bo'lsa yoki o'qituvchining ishtiroki aniq bo'lmay, inson (administrator) tekshiruvi talab etilsa: \"checking\" statusini bering (\"point\": 0).
                        - Agar taqdim etilgan hujjatlar tarbiyaviy ishga aloqador bo'lmasa, yoki ular shunchaki shaxsiy xarakterga ega bo'lsa: \"cancelled\" statusini bering (\"point\": 0).

                        Javobni hech qanday markdown belgilarisiz (```json...``` kabi emas) va qo'shimcha so'zlarsiz, faqatgina quyidagi qat'iy JSON formatida qaytaring:
                        {\"status\": \"accepted|checking|cancelled\", \"point\": <raqam, masalan 0.3 yoki 0>, \"reason\": \"<Qabul qilingan qarorning sababi, tadbir turi va o'qituvchining ishtiroki haqida qisqacha izoh>\"}",
                    ],
                    [
                        'name' => [
                            'uz' => 'Olingan davlat mukofotlari va respublika darajasidagi tashakkurnomalar',
                            'kaa' => 'Alınǵan mámleketlik sıylıqlar hám respublika dárejesindegi alǵısnamalar',
                            'ru' => 'Полученные государственные награды и благодарности республиканского уровня',
                            'en' => 'Received state awards and letters of appreciation at the national level',
                        ],
                        'desc' => [
                            'uz' => 'Guvohnoma yoki respublika darajasidagi tashakkurnomaning muhr bosilgan va imzolangan nusxasi',
                            'kaa' => 'Gúwalıq yamasa respublika dárejesindegi minnetdarshılıqtıń mór basılǵan hám qol qoyılǵan nusqası.',
                            'ru' => 'Копия удостоверения или благодарности республиканского уровня, заверенная печатью и подписью',
                            'en' => 'A stamped and signed copy of the certificate or national-level letter of appreciation',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'ai',
                        'template' => '0',
                        'res_type' => 'all',
                        'upload' => '1', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 2,
                            'no_degrees' => 1,
                            'foreign_lang' => 2,
                            'physical' => 2,
                        ],
                        'year' => 2025,
                        'formula_id' => 1,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => "Siz qat'iy AI baholovchisiz. Taqdim etilgan hujjatlarni (Davlat mukofoti guvohnomasi, orden/medal diplomi yoki respublika darajasidagi rasmiy tashakkurnoma) tahlil qiling.
                        Baholash qoidalari jami %pointing% ballgacha:
                        1. Hujjatda davlat mukofoti (orden, medal, faxriy unvon) yoki Vazirliklar/Oliy idoralar tomonidan berilgan respublika darajasidagi rasmiy tashakkurnoma ekanligini tekshiring.
                        2. Hujjatda rasmiy muhr va mas'ul shaxs imzosi mavjudligini tasdiqlang.
                        3. Baholash: Har bir davlat mukofoti yoki respublika darajasidagi tashakkurnoma uchun 1 ball beriladi.

                        Tahlil natijasiga ko'ra quyidagi qarorlardan birini qabul qiling:
                        - Agar hujjat haqiqiy, respublika darajasida ekanligi va muhr/imzosi tasdiqlansa: \"accepted\" statusini bering va \"point\" qismiga 1 yozing.
                        - Agar hujjatlar xira bo'lsa, mukofotning darajasini (respublika yoki mahalliy ekanligini) aniqlab bo'lmasa, yoxud hujjat shubhali bo'lib inson (administrator) tekshiruvi talab etilsa: \"checking\" statusini bering (\"point\": 0).
                        - Agar taqdim etilgan hujjatlar mahalliy (universitet yoki fakultet) darajadagi bo'lsa, yoki davlat mukofotiga aloqasi bo'lmasa: \"cancelled\" statusini bering (\"point\": 0).

                        Javobni hech qanday markdown belgilarisiz (```json...``` kabi emas) va qo'shimcha so'zlarsiz, faqatgina quyidagi qat'iy JSON formatida qaytaring:
                        {\"status\": \"accepted|checking|cancelled\", \"point\": <raqam, 1 yoki 0>, \"reason\": \"<Qabul qilingan qarorning sababi, mukofot yoki tashakkurnoma turi va darajasi haqida izoh>\"}",
                    ],
                    [
                        'name' => [
                            'uz' => 'Universitetning Ichki tartib qoidalari, odob-ahloq kodeksi hamda mehnat shartnomasida belgilangan vazifalarni o‘z vaqtida va sifatli bajarish holati',
                            'kaa' => 'Universitettiń Ishki tártip qaǵıydaları, ádep-ikramlılıq kodeksi hám de miynet shártnamasında belgilengen wazıypalardı óz waqtında hám sapalı orınlaw jaǵdayı',
                            'ru' => 'Состояние своевременного и качественного выполнения задач, определенных Правилами внутреннего распорядка, кодексом этики и трудовым договором Университета',
                            'en' => 'The state of timely and high-quality fulfillment of tasks defined in the University’s Internal Regulations, Code of Ethics, and employment contract',
                        ],
                        'desc' => [
                            'uz' => 'Intizomiy jazoga tortilmagan bo‘lsa',
                            'kaa' => 'Intizamıy jazaǵa tartılmaǵan bolsa',
                            'ru' => 'Не подвергались дисциплинарному взысканию',
                            'en' => 'Has not been subjected to disciplinary action',
                        ],
                        'observation' => 'current',
                        'report_id' => 1,
                        'checking' => 'pointing',
                        'template' => '0',
                        'res_type' => 'all',
                        'upload' => '0', 'status' => '1',
                        'evaluation' => [
                            'hold_degrees' => 2,
                            'no_degrees' => 3,
                            'foreign_lang' => 1,
                            'physical' => 2,
                        ],
                        'year' => 2025,
                        'formula_id' => 1,
                        'ai_model' => 'gemini-2.5-flash',
                        'ai_prompt' => null,
                    ],
                ],
            ],
        ];

        foreach ($criteria as $criterion) {
            $c = Criterion::create([
                'name' => $criterion['main']['name'],
                'report_id' => $criterion['main']['report_id'],
                'upload' => $criterion['main']['upload'],
                'status' => $criterion['main']['status'],
            ]);
            foreach ($criterion['children'] as $child) {
                $ch = Criterion::create([
                    'name' => $child['name'],
                    'desc' => $child['desc'],
                    'observation' => $child['observation'],
                    'parent_id' => $c->id,
                    'ai_prompt' => $child['ai_prompt'],
                    'ai_model' => $child['ai_model'],
                    'report_id' => $child['report_id'],
                    'checking' => $child['checking'],
                    'res_type' => $child['res_type'],
                    'template' => $child['template'] ?? '1',
                    //'res_type' => 'all',
                    'upload' => $child['upload'],
                    'status' => $child['status'],
                    'formula_id' => $child['formula_id'],
                ]);
                CriterionYear::create([
                    'criterion_id' => $ch->id,
                    'year_id' => $child['year'],
                ]);
                foreach ($child['evaluation'] as $key => $eva) {
                    CriterionEvaluation::create([
                        'criterion_id' => $ch->id,
                        'evaluation' => $key,
                        'score' => $eva
                    ]);
                }
            }
        }
    }
}
