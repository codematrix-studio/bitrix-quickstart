<?php

declare(strict_types=1);

namespace Project\Validation;

class BooleanValidator extends Validator
{
    protected function defaultMessage(): string
    {
        return 'Поле ' . $this->field . ' должно быть булевым';
    }

    public function validate(): bool
    {
        if (is_bool($this->value)) {
            return true;
        }

        if (is_int($this->value) && in_array($this->value, [0, 1], true)) {
            return true;
        }

        if (is_string($this->value) && in_array(strtolower(trim($this->value)), [
            '0', '1', 'true', 'false', 'yes', 'no', 'on', 'off',
        ], true)) {
            return true;
        }

        return $this->fail();
    }
}
