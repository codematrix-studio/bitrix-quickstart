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
        $min = $this->numericBound('min');
        $max = $this->numericBound('max');

        if ($min !== null && $max !== null && $min > $max) {
            throw new \\InvalidArgumentException('Параметр min не может быть больше max.');
        }

        $value = $this->stringValue();

        if ($value === null || trim($value) === '' || !is_numeric(trim($value))) {
            return $this->fail();
        }

        $number = (float) $value;

        if (!is_finite($number)) {
            return $this->fail();
        }

        if ($min !== null && $number < $min) {
            return $this->fail();
        }

        if ($max !== null && $number > $max) {
            return $this->fail();
        }

        return true;
    }

    private function numericBound(string $name): ?float
    {
        if (!isset($this->options[$name])) {
            return null;
        }

        $value = $this->options[$name];

        if (!is_numeric($value) || !is_finite((float) $value)) {
            throw new \\InvalidArgumentException(sprintf('Параметр %s должен быть конечным числом.', $name));
        }

        return (float) $value;
    }
}
