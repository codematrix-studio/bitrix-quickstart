<?php

declare(strict_types=1);

namespace Project\Validation;

class NumericValidator extends Validator
{
    protected function defaultMessage(): string
    {
        return 'Поле ' . $this->field . ' должно быть числом';
    }

    public function validate(): bool
    {
        $value = $this->stringValue();

        if ($value === null || trim($value) === '' || !is_numeric(trim($value))) {
            return $this->fail();
        }

        $number = (float) $value;

        if (!is_finite($number)) {
            return $this->fail();
        }

        if (isset($this->options['min']) && $number < (float) $this->options['min']) {
            return $this->fail();
        }

        if (isset($this->options['max']) && $number > (float) $this->options['max']) {
            return $this->fail();
        }

        return true;
    }
}
