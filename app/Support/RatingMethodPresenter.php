<?php

namespace App\Support;

use App\Models\Criterion;
use App\Models\Formula;

class RatingMethodPresenter
{
    /** @return array{key: string, label: string, explanation: string, note: string, example: string, maximum: float|null} */
    public function describe(Criterion $criterion, string $evaluationCategory): array
    {
        $evaluation = $criterion->criterionEvaluations->firstWhere('evaluation', $evaluationCategory);
        $maximum = $evaluation?->has === '1' ? max(0, (float) $evaluation->score) : null;
        $exampleMaximum = $maximum ?? 5;
        $formattedMaximum = number_format($exampleMaximum, 2, '.', '');

        if ($criterion->isHIndexCriterion()) {
            return [
                'key' => 'h-index',
                'label' => 'H-index bo‘yicha',
                'explanation' => 'Web of Science balli alohida olinadi. Scopus va ResearchGate ballari solishtirilib, faqat kattasi Web of Science balliga qo‘shiladi.',
                'note' => 'Faqat linki va H-index qiymati to‘liq kiritilgan platformalar hisobga olinadi. h=3 uchun ulushning 50%, h=4 uchun 75%, h=5 uchun 100% beriladi; 5 dan yuqori har bir birlik yana 1 ball qo‘shadi.',
                'example' => "Masalan, Web of Science {$formattedMaximum} ball, Scopus 2 ball va ResearchGate 1 ball bo‘lsa: {$formattedMaximum} + max(2, 1) = ".number_format($exampleMaximum + 2, 2, '.', '').' ball.',
                'maximum' => $maximum,
            ];
        }

        return match ($criterion->formula?->code) {
            Formula::Competition => [
                'key' => Formula::Competition,
                'label' => 'Raqobat asosida',
                'explanation' => 'Kriteriyadagi eng yuqori natija maksimal ballni oladi, qolgan natijalar unga nisbatan mutanosib hisoblanadi.',
                'note' => 'Eng yuqori natija o‘zgarsa, shu kriteriyadagi barcha foydalanuvchilarning yakuniy ballari qayta hisoblanadi.',
                'example' => "Eng yuqori natija 10, foydalanuvchi natijasi 8 va maksimal ball {$formattedMaximum} bo‘lsa: {$formattedMaximum} × 8 ÷ 10 = ".number_format($exampleMaximum * 0.8, 2, '.', '').' ball.',
                'maximum' => $maximum,
            ],
            Formula::Maximum => [
                'key' => Formula::Maximum,
                'label' => 'Maksimal ballgacha',
                'explanation' => 'Tasdiqlangan resurslardan to‘plangan ball foydalanuvchi toifasi uchun belgilangan maksimal chegaragacha hisoblanadi.',
                'note' => 'To‘plangan ball chegaradan oshsa, yakuniy natija maksimal ball bilan cheklanadi.',
                'example' => 'To‘plangan ball '.number_format($exampleMaximum + 2, 2, '.', '')." va maksimal ball {$formattedMaximum} bo‘lsa, yakuniy natija {$formattedMaximum} ball bo‘ladi.",
                'maximum' => $maximum,
            ],
            Formula::Unlimited => [
                'key' => Formula::Unlimited,
                'label' => 'Cheklanmagan yig‘indi',
                'explanation' => 'Barcha tasdiqlangan resurslarning ballari qo‘shiladi va yakuniy natijaga to‘liq o‘tadi.',
                'note' => 'Bu usulda kriteriya bo‘yicha umumiy ballga yuqori chegara qo‘yilmaydi.',
                'example' => 'Ikki resurs 2 va 3 ball olsa, yakuniy natija 2 + 3 = 5 ball bo‘ladi.',
                'maximum' => $maximum,
            ],
            default => [
                'key' => 'unconfigured',
                'label' => 'Usul sozlanmagan',
                'explanation' => 'Ushbu kriteriya uchun baholash formulasi biriktirilmagan.',
                'note' => 'Aniq hisoblash usuli administrator tomonidan kriteriya sozlamalarida belgilanadi.',
                'example' => 'Formula belgilanmaguncha yakuniy ballni hisoblash misolini ko‘rsatib bo‘lmaydi.',
                'maximum' => $maximum,
            ],
        };
    }
}
