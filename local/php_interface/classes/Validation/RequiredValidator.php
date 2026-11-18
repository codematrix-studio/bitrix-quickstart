<?php

declare(strict_types=1);

namespace Project\Validation;

class RequiredValidator extends Validator
{
    protected function defaultMessage(): string
    {
        return 'Введите ' . $this->field;
    }

    public function validate(): bool
    {
        if ($this->value === null) {
            return $this->fail();
        }

        if (is_string($this->value)) {
            return trim($this->value) !== '' ?: $this->fail();
        }

        if (is_array($this->value)) {
            return $this->value !== [] ?: $this->fail();
        }

        if (is_float($this->value) && !is_finite($this->value)) {
            return $this->fail();
        }

        // Ноль и false — заданные значения, а не пустое поле.
        return true;
    }
}
