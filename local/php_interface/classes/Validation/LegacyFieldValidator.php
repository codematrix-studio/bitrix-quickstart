<?php

declare(strict_types=1);

namespace Project\Validation;

/**
 * Базовый адаптер интерфейса валидаторов из lema.lib.
 *
 * Поддерживает старую сигнатуру конструктора и методы validate(), getError()
 * и showAllErrors(). Для пользовательского валидатора можно переопределить validate().
 */
class LegacyFieldValidator
{
    protected $checkField = null;
    protected $checkValue = null;
    protected $errorMessage = null;
    protected array $additionalParams = [];
    protected bool $hasError = false;
    protected $error = null;
    protected bool $showAllErrors = false;
    protected string $ruleName = 'required';

    public function __construct($name, $value, array $additional = [])
    {
        $this->checkField = $name;
        $this->checkValue = $value;
        $this->setAdditionalParams($additional);
        $this->validate();
    }

    public function validate(): bool
    {
        $form = new FormValidator(
            [[$this->checkField, $this->ruleName, $this->additionalParams]],
            [$this->checkField => $this->checkValue]
        );

        $form->setRecaptchaVerifier($this->additionalParams['verifier'] ?? null);
        $valid = $form->validate();
        $this->hasError = !$valid;
        $this->showAllErrors = (bool) ($this->additionalParams['showAllErrors'] ?? false);
        $errors = $form->getErrors();
        $this->error = $errors[$this->checkField] ?? null;

        if ($this->errorMessage !== null && $this->error !== null) {
            $this->error = (string) $this->errorMessage;
        }

        return $valid;
    }

    public function getError()
    {
        return $this->error;
    }

    public function showAllErrors(): bool
    {
        return $this->showAllErrors;
    }

    protected function setAdditionalParams(array $additional = []): void
    {
        $this->additionalParams = $additional;
        $this->showAllErrors = (bool) ($additional['showAllErrors'] ?? false);
        if (isset($additional['message']) && is_scalar($additional['message'])) {
            $this->errorMessage = (string) $additional['message'];
        }
    }

    protected function setErrorMessage($errorMessage = null): void
    {
        if (is_string($errorMessage)) {
            $this->errorMessage = $errorMessage;
        }
    }

    protected function setError(): void
    {
        $this->error = $this->errorMessage;
    }

    protected function setDataMessage($message): void
    {
        $this->errorMessage = (string) $message;
    }

    protected function setDataShowAllErrors($showAll): void
    {
        $this->showAllErrors = (bool) $showAll;
    }
}
