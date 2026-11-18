<?php

declare(strict_types=1);

namespace Project\Validation;

class EmailValidator extends Validator
{
    protected function defaultMessage(): string
    {
        return 'Неверный формат E-mail';
    }

    public function validate(): bool
    {
        $value = $this->stringValue();

        return $value !== null && filter_var(trim($value), FILTER_VALIDATE_EMAIL) !== false
            ? true
            : $this->fail();
    }
}
