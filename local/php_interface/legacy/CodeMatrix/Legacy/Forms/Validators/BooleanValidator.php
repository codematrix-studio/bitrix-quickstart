<?php

namespace CodeMatrix\Legacy\Forms\Validators;

/**
 * Сохраняет поведение старого Lema BooleanValidator:
 * валидным считается значение, которое фильтр PHP распознаёт как true.
 */
class BooleanValidator extends Validator
{
    public const DEFAULT_MESSAGE = 'Поле {name} должно быть булевым';

    protected string $ruleName = 'boolean';

    public function validate(): bool
    {
        $value = is_scalar($this->checkValue) ? $this->checkValue : null;
        $valid = $value !== null && filter_var($value, FILTER_VALIDATE_BOOLEAN) === true;

        $this->hasError = !$valid;
        $this->error = $valid ? null : $this->formatLegacyMessage($this->errorMessage ?? self::DEFAULT_MESSAGE);

        return $valid;
    }
}
