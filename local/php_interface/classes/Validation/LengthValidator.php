<?php

declare(strict_types=1);

namespace Project\Validation;

class LengthValidator extends Validator
{
    protected function defaultMessage(): string
    {
        if (isset($this->options['min'], $this->options['max'])) {
            return 'Поле ' . $this->field . ' должно содержать от ' . $this->options['min'] . ' до ' . $this->options['max'] . ' символов';
        }

        if (isset($this->options['min'])) {
            return 'Поле ' . $this->field . ' должно содержать не менее ' . $this->options['min'] . ' символов';
        }

        if (isset($this->options['max'])) {
            return 'Поле ' . $this->field . ' должно содержать не более ' . $this->options['max'] . ' символов';
        }

        return 'Некорректная длина поля ' . $this->field;
    }

    public function validate(): bool
    {
        $min = $this->integerBound('min');
        $max = $this->integerBound('max');

        if ($min !== null && $max !== null && $min > $max) {
            throw new \InvalidArgumentException('Параметр min не может быть больше max.');
        }

        $value = $this->stringValue();

        if ($value === null) {
            return $this->fail();
        }

        $value = trim($value);
        $length = preg_match_all('/\\X/u', $value, $matches);

        if ($length === false) {
            return $this->fail();
        }

        if ($min !== null && $length < $min) {
            return $this->fail();
        }
        if ($max !== null && $length > $max) {
            return $this->fail();
        }

        return true;
    }

    private function integerBound(string $name): ?int
    {
        if (!isset($this->options[$name])) {
            return null;
        }

        $value = $this->options[$name];

        if (!is_numeric($value)) {
            throw new \InvalidArgumentException(sprintf('Параметр %s должен быть неотрицательным целым числом.', $name));
        }

        $number = (float) $value;

        if (!is_finite($number) || $number < 0 || floor($number) !== $number || $number > PHP_INT_MAX) {
            throw new \InvalidArgumentException(sprintf('Параметр %s должен быть неотрицательным целым числом.', $name));
        }

        return (int) $number;
    }
}
