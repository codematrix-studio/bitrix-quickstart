<?php

namespace CodeMatrix\Legacy\Forms\Validators;

/**
 * Сохраняет целочисленную семантику старого Lema NumericValidator.
 *
 * В отличие от современного Project\Validation\NumericValidator,
 * legacy-правило numerical не принимает дробные значения.
 */
class NumericValidator extends Validator
{
    public const DEFAULT_MESSAGE = 'Поле {name} должно быть числом';

    protected string $ruleName = 'numeric';

    public function validate(): bool
    {
        $value = is_scalar($this->checkValue) ? (string) $this->checkValue : null;
        $valid = $value !== null
            && trim($value) !== ''
            && filter_var(trim($value), FILTER_VALIDATE_INT) !== false;

        $this->hasError = !$valid;
        $this->error = $valid ? null : $this->formatLegacyMessage($this->errorMessage ?? self::DEFAULT_MESSAGE);

        return $valid;
    }
}
