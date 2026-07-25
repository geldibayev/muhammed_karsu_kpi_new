<?php

namespace App\Actions;

use App\Models\Datum;

class GetResourceStatusStatistics
{
    /**
     * @return array{
     *     total: int,
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
        $statistics = Datum::query()
            ->toBase()
            ->selectRaw('COUNT(*) as total')
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
