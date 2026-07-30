<?php

namespace App\Actions;

use GuzzleHttp\Exception\ConnectException as GuzzleConnectException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Throwable;
use UnexpectedValueException;

class DescribeHemisLoginFailure
{
    public function handle(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof IdentityProviderException => 'HEMIS avtorizatsiya xizmati so‘rovni rad etdi. Qaytadan urinib ko‘ring.',
            $exception instanceof ConnectionException,
            $exception instanceof GuzzleConnectException => 'HEMIS xizmatiga ulanib bo‘lmadi. Internet, DNS yoki HEMIS xizmati holatini tekshirib, qayta urinib ko‘ring.',
            $exception instanceof RequestException => 'HEMIS xizmati foydalanuvchi ma’lumotlarini olishda xatolik qaytardi.',
            $exception instanceof UnexpectedValueException => 'HEMIS profilingizdagi tizimga kirish uchun zarur ma’lumotlar to‘liq emas.',
            default => 'HEMIS orqali kirishda kutilmagan xatolik yuz berdi. Keyinroq qayta urinib ko‘ring.',
        };
    }
}
