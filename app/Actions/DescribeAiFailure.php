<?php

namespace App\Actions;

use Throwable;

class DescribeAiFailure
{
    public function handle(Throwable|string|null $failure): string
    {
        $message = mb_strtolower(
            $failure instanceof Throwable ? $failure->getMessage() : (string) $failure,
            'UTF-8',
        );

        return match (true) {
            str_contains($message, '429'),
            str_contains($message, 'quota'),
            str_contains($message, 'rate limit') => 'AI xizmatining so‘rov limiti tugagan. Limit yangilanishi yoki tarif sozlamasi tekshirilishi kerak.',

            str_contains($message, 'timed out'),
            str_contains($message, 'timeout') => 'AI xizmatidan belgilangan vaqt ichida javob kelmadi.',

            str_contains($message, '401'),
            str_contains($message, '403'),
            str_contains($message, 'api key'),
            str_contains($message, 'unauthenticated') => 'AI xizmatiga kirish kaliti yoki ruxsat sozlamasi noto‘g‘ri.',

            str_contains($message, 'connection'),
            str_contains($message, 'could not resolve'),
            str_contains($message, 'network') => 'AI xizmatiga tarmoq orqali ulanib bo‘lmadi.',

            default => 'AI tekshiruvi kutilmagan xato sabab yakunlanmadi. Inson tekshiruvi zarur.',
        };
    }
}
