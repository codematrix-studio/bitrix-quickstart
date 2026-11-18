<?php

declare(strict_types=1);

namespace Project\Validation;

/**
 * Адаптер старого контракта валидаторов lema.lib.
 *
 * Сохраняет имена свойств и вспомогательных методов, которыми могли пользоваться
 * пользовательские валидаторы, написанные для старой библиотеки.
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
    protected array $messageReplacements = [];

    public function __construct($name, $value, array $additional = [])
    {
        $this->checkField = $name;
        $this->checkValue = $value;
        $this->setAdditionalParams($additional);
        $this->setErrorMessage();
        // Старая библиотека выполняла проверку непосредственно в конструкторе.
        $this->validate();
    }

    public function validate(): bool
    {
        $form = new FormValidator(
            [[$this->checkField, $this->ruleName, $this->additionalParams]],
            [$this->checkField => $this->checkValue]
        );

        $verifier = $this->additionalParams['verifier'] ?? null;
        $form->setRecaptchaVerifier(is_callable($verifier) ? $verifier : null);
        $valid = $form->validate();
        $this->hasError = !$valid;
        $this->showAllErrors = (bool) ($this->additionalParams['showAllErrors'] ?? $this->additionalParams['show_all_errors'] ?? false);
        $errors = $form->getErrors();
        $this->error = $errors[$this->checkField] ?? null;

        if (!$valid && $this->errorMessage !== null) {
            $this->error = $this->formatLegacyMessage($this->errorMessage);
        }

        return $valid;
    }

    public function isValid(): bool
    {
        return !$this->hasError;
    }

    public function getError()
    {
        if ($this->error !== null) {
            return $this->error;
        }

        if ($this->errorMessage !== null) {
            return $this->formatLegacyMessage($this->errorMessage);
        }

        $constant = static::class . '::DEFAULT_MESSAGE';

        return defined($constant) && constant($constant) !== ''
            ? $this->formatLegacyMessage((string) constant($constant))
            : null;
    }

    public function showAllErrors(): bool
    {
        return $this->showAllErrors;
    }

    protected function setAdditionalParams(array $additional = []): void
    {
        $this->additionalParams = $additional;

        foreach ($additional as $name => $value) {
            $method = 'setData' . ucfirst((string) $name);
            if (method_exists($this, $method)) {
                $this->{$method}($value);
            }
        }

        $this->showAllErrors = (bool) ($additional['showAllErrors'] ?? $additional['show_all_errors'] ?? false);
    }

    protected function setErrorMessage($errorMessage = null): void
    {
        if (is_string($errorMessage)) {
            $this->errorMessage = $errorMessage;
            return;
        }

        $constant = static::class . '::DEFAULT_MESSAGE';
        if ($this->errorMessage === null && defined($constant) && constant($constant) !== '') {
            $this->errorMessage = (string) constant($constant);
        }
    }

    protected function setError(): void
    {
        $this->error = $this->errorMessage === null
            ? null
            : $this->formatLegacyMessage($this->errorMessage);
    }

    protected function setDataMessage($message): void
    {
        if (is_string($message)) {
            $this->errorMessage = $message;
            return;
        }

        if (is_array($message) && isset($message[0]) && is_scalar($message[0])) {
            $this->errorMessage = (string) $message[0];
            $this->messageReplacements = isset($message[1]) && is_array($message[1])
                ? $message[1]
                : [];
        }
    }

    protected function setDataShowAllErrors($showAll): void
    {
        $this->showAllErrors = (bool) $showAll;
    }

    protected function formatLegacyMessage(string $message): string
    {
        $replace = ['{name}' => (string) $this->checkField];

        foreach ($this->additionalParams as $name => $value) {
            if (is_scalar($value)) {
                $replace['{' . $name . '}'] = (string) $value;
            }
        }

        foreach ($this->messageReplacements as $name => $value) {
            if (is_scalar($value)) {
                $replace['{' . $name . '}'] = (string) $value;
            }
        }

        return strtr($message, $replace);
    }
}
