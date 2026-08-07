<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class ResourceUploadWindow
{
    private const UZBEK_MONTH_NAMES = [
        1 => 'yanvar',
        2 => 'fevral',
        3 => 'mart',
        4 => 'aprel',
        5 => 'may',
        6 => 'iyun',
        7 => 'iyul',
        8 => 'avgust',
        9 => 'sentabr',
        10 => 'oktabr',
        11 => 'noyabr',
        12 => 'dekabr',
    ];

    public function deadline(): CarbonImmutable
    {
        return CarbonImmutable::parse(
            (string) config('kpi.resource_upload_deadline'),
            $this->timezone(),
        );
    }

    public function isOpen(): bool
    {
        return CarbonImmutable::now($this->timezone())->lessThanOrEqualTo($this->deadline());
    }

    public function formattedDeadline(): string
    {
        $deadline = $this->deadline();

        return sprintf(
            '%d-yil %d-%s, %s',
            $deadline->year,
            $deadline->day,
            self::UZBEK_MONTH_NAMES[$deadline->month],
            $deadline->format('H:i'),
        );
    }

    private function timezone(): string
    {
        return (string) config('app.timezone', 'Asia/Tashkent');
    }
}
