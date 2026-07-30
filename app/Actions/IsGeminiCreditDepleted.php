<?php

namespace App\Actions;

use Gemini\Exceptions\ErrorException;
use Throwable;

class IsGeminiCreditDepleted
{
    public function handle(Throwable|string|null $failure): bool
    {
        if (is_string($failure)) {
            return $this->containsDepletedCreditMessage($failure);
        }

        while ($failure instanceof Throwable) {
            if ($failure instanceof ErrorException
                && $failure->getErrorStatus() === 'RESOURCE_EXHAUSTED'
                && $this->containsDepletedCreditMessage($failure->getErrorMessage())) {
                return true;
            }

            $failure = $failure->getPrevious();
        }

        return false;
    }

    private function containsDepletedCreditMessage(string $message): bool
    {
        $message = mb_strtolower($message, 'UTF-8');

        return str_contains($message, 'prepayment credits are depleted')
            || str_contains($message, 'prepaid credits are depleted');
    }
}
