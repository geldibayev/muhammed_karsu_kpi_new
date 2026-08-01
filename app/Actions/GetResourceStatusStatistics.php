<?php

namespace App\Actions;

use App\Models\Datum;
use Carbon\CarbonImmutable;

class GetResourceStatusStatistics
{
    /**
     * @return array{
     *     total: int,
     *     uploads: array{
     *         today: int,
     *         current_week: int,
     *         today_label: string,
     *         current_week_label: string
     *     },
     *     statuses: array<int, array{
     *         value: string,
     *         label: string,
     *         description: string,
     *         count: int,
     *         percentage: float
     *     }>
     * }
     */
    public function handle(): array
    {
        $now = CarbonImmutable::now();
        $todayStartsAt = $now->startOfDay();
        $tomorrowStartsAt = $todayStartsAt->addDay();
        $weekStartsAt = $now->startOfWeek();

        $statistics = Datum::query()
            ->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw(
                'SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) as uploaded_today',
                [$todayStartsAt, $tomorrowStartsAt],
            )
            ->selectRaw(
                'SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) as uploaded_current_week',
                [$weekStartsAt, $tomorrowStartsAt],
            )
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as received',
                ['received'],
            )
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as checking',
                ['checking'],
            )
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as accepted',
                ['accepted'],
            )
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as cancelled',
                ['cancelled'],
            )
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as deleted',
                ['deleted'],
            )
            ->first();
        $total = (int) ($statistics?->total ?? 0);

        $statuses = array_map(
            static function (array $status) use ($statistics, $total): array {
                $count = (int) ($statistics->{$status['value']} ?? 0);

                return [
                    ...$status,
                    'count' => $count,
                    'percentage' => $total > 0
                        ? round(($count / $total) * 100, 1)
                        : 0.0,
                ];
            },
            $this->statusDefinitions(),
        );

        return [
            'total' => $total,
            'uploads' => [
                'today' => (int) ($statistics?->uploaded_today ?? 0),
                'current_week' => (int) ($statistics?->uploaded_current_week ?? 0),
                'today_label' => $todayStartsAt->format('d.m.Y'),
                'current_week_label' => $weekStartsAt->format('d.m.Y').' — '.$todayStartsAt->format('d.m.Y'),
            ],
            'statuses' => $statuses,
        ];
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    private function statusDefinitions(): array
    {
        return [
            [
                'value' => 'received',
                'label' => 'Yuborilgan',
                'description' => 'Tekshiruvga yuborilgan yangi resurslar.',
            ],
            [
                'value' => 'checking',
                'label' => 'Ko‘rib chiqilmoqda',
                'description' => 'Hozirda AI yoki mas’ul tomonidan tekshirilayotgan resurslar.',
            ],
            [
                'value' => 'accepted',
                'label' => 'Tasdiqlangan',
                'description' => 'Talablarga mos deb topilgan resurslar.',
            ],
            [
                'value' => 'cancelled',
                'label' => 'Qaytarilgan',
                'description' => 'Kamchiligi sabab foydalanuvchiga qaytarilgan resurslar.',
            ],
            [
                'value' => 'deleted',
                'label' => 'O‘chirilgan',
                'description' => 'Tizimda o‘chirilgan holatda saqlanayotgan resurslar.',
            ],
        ];
    }
}
