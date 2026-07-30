<?php

namespace App\Actions;

use Gemini\Exceptions\ErrorException;
use Throwable;

class DescribeAiFailure
{
    public const DOCUMENT_WITHOUT_PAGES_REASON = 'Yuklangan hujjatda AI o‘qiy oladigan sahifa topilmadi. Inson tekshiruvi zarur.';

    public function handle(Throwable|string|null $failure): string
    {
        if ($this->isDocumentWithoutPages($failure)) {
            return self::DOCUMENT_WITHOUT_PAGES_REASON;
        }

        if ($failure instanceof ErrorException) {
            return $this->geminiErrorReason($failure);
        }

        $message = mb_strtolower(
            $failure instanceof Throwable ? $this->exceptionMessages($failure) : (string) $failure,
            'UTF-8',
        );

        return match (true) {
            str_contains($message, 'no such file'),
            str_contains($message, 'file not found'),
            str_contains($message, 'unable to read'),
            str_contains($message, 'failed to open stream') => 'Tekshiriladigan resurs fayli server storage’ida topilmadi yoki o‘qib bo‘lmadi.',

            str_contains($message, '413'),
            str_contains($message, 'payload too large'),
            str_contains($message, 'request entity too large') => 'Tekshiriladigan resurs Gemini so‘rovi uchun ruxsat etilgan hajmdan katta.',

            str_contains($message, 'unsupported media'),
            str_contains($message, 'unsupported mime'),
            str_contains($message, 'mime type') => 'Tekshiriladigan resursning fayl turi AI tomonidan qo‘llab-quvvatlanmaydi.',

            str_contains($message, '429'),
            str_contains($message, 'quota'),
            str_contains($message, 'rate limit') => 'AI xizmatining so‘rov limiti tugagan. Limit yangilanishi yoki tarif sozlamasi tekshirilishi kerak.',

            str_contains($message, 'timed out'),
            str_contains($message, 'timeout') => 'AI xizmatidan belgilangan vaqt ichida javob kelmadi.',

            str_contains($message, '401'),
            str_contains($message, '403'),
            str_contains($message, 'api key'),
            str_contains($message, 'unauthenticated') => 'AI xizmatiga kirish kaliti yoki ruxsat sozlamasi noto‘g‘ri.',

            str_contains($message, '404'),
            str_contains($message, 'model not found'),
            str_contains($message, 'models/') && str_contains($message, 'not found') => 'Kriteriyada ko‘rsatilgan Gemini modeli topilmadi yoki ushbu API versiyasida ishlamaydi.',

            str_contains($message, '400'),
            str_contains($message, 'invalid argument'),
            str_contains($message, 'invalid schema') => 'Gemini kriteriyadagi model yoki JSON sxema bilan yuborilgan so‘rov formatini qabul qilmadi.',

            str_contains($message, 'ssl'),
            str_contains($message, 'certificate'),
            str_contains($message, 'curl error') => 'Server Gemini xizmatiga xavfsiz SSL ulanishini o‘rnata olmadi.',

            str_contains($message, 'connection'),
            str_contains($message, 'could not resolve'),
            str_contains($message, 'network') => 'AI xizmatiga tarmoq orqali ulanib bo‘lmadi.',

            default => 'AI tekshiruvi kutilmagan xato sabab yakunlanmadi. Inson tekshiruvi zarur.',
        };
    }

    public function isDocumentWithoutPages(Throwable|string|null $failure): bool
    {
        $message = $failure instanceof Throwable
            ? $this->exceptionMessages($failure)
            : (string) $failure;

        return str_contains(mb_strtolower($message, 'UTF-8'), 'document has no pages');
    }

    private function geminiErrorReason(ErrorException $exception): string
    {
        if (str_contains(
            mb_strtolower($exception->getErrorMessage(), 'UTF-8'),
            'prepayment credits are depleted',
        )) {
            return 'Gemini API oldindan to‘lov krediti tugagan. AI Studio billing hisobiga kredit qo‘shish kerak.';
        }

        return match ($exception->getErrorCode()) {
            400 => 'Gemini kriteriyadagi model yoki JSON sxema bilan yuborilgan so‘rov formatini qabul qilmadi.',
            401, 403 => 'AI xizmatiga kirish kaliti yoki ruxsat sozlamasi noto‘g‘ri.',
            404 => 'Kriteriyada ko‘rsatilgan Gemini modeli topilmadi yoki ushbu API versiyasida ishlamaydi.',
            413 => 'Tekshiriladigan resurs Gemini so‘rovi uchun ruxsat etilgan hajmdan katta.',
            429 => 'AI xizmatining so‘rov limiti tugagan. Limit yangilanishi yoki tarif sozlamasi tekshirilishi kerak.',
            500, 502, 503, 504 => 'Gemini xizmatining o‘zida vaqtinchalik server xatosi yuz berdi.',
            default => $this->handle($exception->getErrorStatus().' '.$exception->getErrorMessage()),
        };
    }

    private function exceptionMessages(Throwable $exception): string
    {
        $messages = [];

        do {
            $messages[] = $exception->getMessage();
            $exception = $exception->getPrevious();
        } while ($exception instanceof Throwable);

        return implode(' ', $messages);
    }
}
