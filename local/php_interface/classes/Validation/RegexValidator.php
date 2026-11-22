<?php

declare(strict_types=1);

namespace Project\Validation;

class RegexValidator extends Validator
{
    protected function defaultMessage(): string
    {
        return 'Неверный формат ' . $this->field;
    }

    public function validate(): bool
    {
        $pattern = $this->options['pattern'] ?? null;
        $value = $this->stringValue();

        if ($value === null) {
            return $this->fail();
        }

        if (!is_string($pattern) || $pattern === '') {
            throw new \InvalidArgumentException('Для правила regex необходимо передать непустой параметр pattern.');
        }

        $result = @preg_match($pattern, $value);

        if ($result === false) {
            throw new \InvalidArgumentException('Передан некорректный шаблон регулярного выражения.');
        }

        return $result === 1 ? true : $this->fail();
    }
}
