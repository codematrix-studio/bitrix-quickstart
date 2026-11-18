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
        $value = $this->stringValue();

        if ($value === null) {
            return $this->fail();
        }

        $value = trim($value);
        $length = preg_match_all('/\X/u', $value, $matches);

        if ($length === false) {
            return $this->fail();
        }

        $min = $this->options['min'] ?? null;
        $max = $this->options['max'] ?? null;

        if ($min !== null && (!is_numeric($min) || (int) $min < 0)) {
            throw new \InvalidArgumentException('Параметр min должен быть неотрицательным числом.');
        }
        if ($max !== null && (!is_numeric($max) || (int) $max < 0)) {
            throw new \InvalidArgumentException('Параметр max должен быть неотрицательным числом.');
        }
        if ($min !== null && $max !== null && (int) $min > (int) $max) {
            throw new \InvalidArgumentException('Параметр min не может быть больше max.');
        }

        if ($min !== null && $length < (int) $min) {
            return $this->fail();
        }
        if ($max !== null && $length > (int) $max) {
            return $this->fail();
        }

        return true;
    }
}
