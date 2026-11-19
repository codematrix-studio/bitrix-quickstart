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

        $validator = new \Project\Support\EmailValidator();

        return $value !== null && $validator->isValid($value)
            ? true
            : $this->fail();
    }
}
