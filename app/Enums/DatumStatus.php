<?php

namespace App\Enums;

enum DatumStatus: string
{
    case Received = 'received';
    case Checking = 'checking';
    case Accepted = 'accepted';
    case Cancelled = 'cancelled';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Yuborilgan',
            self::Checking => 'Tekshirilmoqda',
            self::Accepted => 'Tasdiqlangan',
            self::Cancelled => 'Qaytarilgan',
            self::Deleted => 'O‘chirilgan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Received => 'badge-primary',
            self::Checking => 'badge-warning',
            self::Accepted => 'badge-success',
            self::Cancelled => 'badge-danger',
            self::Deleted => 'badge-secondary',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Received => 'Tekshiruvchiga yuborilgan va ko‘rib chiqishni kutayotgan resurslar.',
            self::Checking => 'Hozirda AI yoki mas’ul tekshiruvchi tomonidan ko‘rib chiqilayotgan resurslar.',
            self::Accepted => 'Talablarga mos deb topilib, tasdiqlangan resurslar.',
            self::Cancelled => 'Kamchiliklari sabab qaytarilgan resurslar.',
            self::Deleted => 'Foydalanuvchi tomonidan o‘chirilgan yoki dublikat sifatida hisobdan chiqarilgan resurslar.',
        };
    }
}
