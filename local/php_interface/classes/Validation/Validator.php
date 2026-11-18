<?php

declare(strict_types=1);

namespace Project\Validation;

/**
 * Базовый класс валидатора.
 *
 * Валидатор хранит имя поля, исходное значение и параметры правила.
 * Проверка выполняется явно через validate(), ошибки доступны через getError().
 */
abstract class Validator
{
    protected string $field;
    protected mixed $value;
    protected array $options;
    protected ?string $error = null;

    public function __construct(string $field, mixed $value, array $options = [])
    {
        $this->field = $field;
        $this->value = $value;
        $this->options = $options;
    }

    abstract public function validate(): bool;

    public function getError(): ?string
    {
        return $this->error ?? $this->defaultMessage();
    }

    public function showAllErrors(): bool
    {
        return (bool) ($this->options['showAllErrors'] ?? $this->options['show_all_errors'] ?? false);
    }

    protected function defaultMessage(): string
    {
        return 'Поле ' . $this->field . ' заполнено неверно';
    }

    protected function fail(?string $message = null): bool
    {
        $this->error = $message ?? (string) ($this->options['message'] ?? $this->defaultMessage());

        return false;
    }

    protected function isScalarValue(): bool
    {
        return is_scalar($this->value) || $this->value instanceof \Stringable;
    }

    protected function stringValue(): ?string
    {
        return $this->isScalarValue() ? (string) $this->value : null;
    }
}
