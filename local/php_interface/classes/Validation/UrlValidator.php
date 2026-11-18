<?php

declare(strict_types=1);

namespace Project\Validation;

class UrlValidator extends Validator
{
    protected function defaultMessage(): string
    {
        return 'Неверный формат URL';
    }

    public function validate(): bool
    {
        $value = $this->stringValue();

        return $value !== null && filter_var(trim($value), FILTER_VALIDATE_URL) !== false
            ? true
            : $this->fail();
    }
}
