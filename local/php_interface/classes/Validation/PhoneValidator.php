<?php

declare(strict_types=1);

namespace Project\Validation;

class PhoneValidator extends Validator
{
    protected function defaultMessage(): string
    {
        return 'Неверный формат телефона';
    }

    public function validate(): bool
    {
        $value = $this->stringValue();

        if ($value === null) {
            return $this->fail();
        }

        // Сохраняем прежний контракт: российские номера с кодом +7 или 8,
        // а также локальный формат из десяти цифр.
        $pattern = '~^\s*(?:(?:\+?\s*7)|(?:\s*8\s*))?(?:\s*-?\s*)?(?:\(\d{3}\)|\d{3})(?:\s*-?\s*)?\d{3}(?:\s*-?\s*)?\d{2}(?:\s*-?\s*)?\d{2}$~';

        return preg_match($pattern, trim($value)) === 1 ? true : $this->fail();
    }
}
