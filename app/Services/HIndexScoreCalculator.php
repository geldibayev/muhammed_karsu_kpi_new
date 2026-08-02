<?php

namespace App\Services;

class HIndexScoreCalculator
{
    /**
     * @param  array<string, array{link: string, value: int|string}>  $profiles
     * @return array{total: float, summary: string}
     */
    public function calculate(array $profiles, float $maximumShare): array
    {
        $scores = [];
        $summary = [];
        $labels = [
            'scopus' => 'Scopus',
            'web_of_science' => 'Web of Science',
            'research_gate' => 'Research Gate',
        ];

        foreach ($labels as $key => $label) {
            $profile = $profiles[$key] ?? null;

            if (! is_array($profile) || ! filled($profile['link'] ?? null) || ! array_key_exists('value', $profile)) {
                continue;
            }

            $value = (int) data_get($profiles, $key.'.value', 0);
            $score = round($this->score($value, $maximumShare), 2);
            $scores[] = $score;
            $summary[] = $label." h={$value}: ".number_format($score, 2, '.', '').' ball';
        }

        return [
            'total' => round(array_sum($scores), 2),
            'summary' => implode('; ', $summary),
        ];
    }

    public function score(int $hIndex, float $maximumShare): float
    {
        $percentage = match (true) {
            $hIndex >= 5 => 1,
            $hIndex === 4 => 0.75,
            $hIndex === 3 => 0.5,
            default => 0.25,
        };

        return ($maximumShare * $percentage) + max(0, $hIndex - 5);
    }
}
