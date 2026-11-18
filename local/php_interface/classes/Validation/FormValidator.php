<?php

declare(strict_types=1);

namespace Project\Validation;

use Closure;
use InvalidArgumentException;

/**
 * Проверяет набор полей по правилам, совместимым с форматом lema.lib.
 *
 * Формат правила: [ "email, phone", "email", ["message" => "..."] ].
 * Класс не очищает HTML и не экранирует вывод.
 */
class FormValidator
{
    protected array $fields = [];
    protected array $rules = [];
    protected array $errors = [];
    protected ?Closure $recaptchaVerifier = null;

    public function __construct(array $rules = [], array $fields = [])
    {
        if ($fields !== []) {
            $this->setFields($fields);
        }
        if ($rules !== []) {
            $this->setRules($rules);
        }
    }

    public function rules(): array
    {
        return $this->rules;
    }

    public function setRules(array $rules = []): static
    {
        $this->rules = $rules;

        return $this;
    }

    /**
     * Удаляет только внешние пробелы у строк. Массивы и прочие типы сохраняются.
     */
    public function setFields(array $fields = [], $clean = true): static
    {
        if ($clean) {
            $this->fields = [];
            $this->errors = [];
        }

        foreach ($fields as $name => $value) {
            $this->fields[$name] = is_string($value) ? trim($value) : $value;
        }

        return $this;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function getField($name)
    {
        return $this->fields[$name] ?? null;
    }

    public function getFieldCP1251($name)
    {
        $value = $this->getField($name);

        if (!is_string($value)) {
            return $value;
        }

        $converted = iconv('UTF-8', 'Windows-1251//TRANSLIT', $value);

        return $converted === false ? null : $converted;
    }

    /**
     * Задаёт проверяющую функцию CAPTCHA. Без неё проверка всегда завершается отказом.
     */
    public function setRecaptchaVerifier(?callable $verifier): static
    {
        $this->recaptchaVerifier = $verifier === null ? null : Closure::fromCallable($verifier);

        return $this;
    }

    public function validate(): bool
    {
        // Повторная проверка всегда формирует ошибки заново.
        $this->errors = [];
        $validatorMap = $this->validators();

        foreach ($this->rules as $rule) {
            if (!is_array($rule) || !isset($rule[0], $rule[1]) || !is_string($rule[1])) {
                continue;
            }

            $ruleName = strtolower($rule[1]);
            $params = isset($rule[2]) && is_array($rule[2]) ? $rule[2] : [];
            $fieldNames = is_array($rule[0])
                ? $rule[0]
                : preg_split('/\s*,\s*/u', (string) $rule[0], -1, PREG_SPLIT_NO_EMPTY);

            if ($fieldNames === false) {
                continue;
            }

            $validatorDefinition = $validatorMap[$ruleName] ?? null;

            foreach ($fieldNames as $fieldName) {
                $fieldName = (string) $fieldName;
                if ($fieldName === '') {
                    continue;
                }

                $value = $this->fields[$fieldName] ?? null;
                [$valid, $message, $showAllErrors] = $this->runValidator(
                    $validatorDefinition,
                    $ruleName,
                    $fieldName,
                    $value,
                    $params
                );

                if (!$valid) {
                    $this->setError($fieldName, $this->formatMessage($message, $fieldName, $params), $showAllErrors);
                }
            }
        }

        return $this->errors === [];
    }

    public function getErrors($splitSym = null): array
    {
        if ($splitSym === null) {
            return $this->errors;
        }

        $result = [];
        foreach ($this->errors as $name => $messages) {
            $result[$name] = is_array($messages) ? implode((string) $splitSym, $messages) : $messages;
        }

        return $result;
    }

    public function getErrorsCP1251($splitSym = null): array
    {
        $errors = $this->getErrors($splitSym);

        foreach ($errors as $name => $messages) {
            if (is_array($messages)) {
                $errors[$name] = array_map(
                    static fn ($message) => self::convertToWindows1251((string) $message),
                    $messages
                );
            } else {
                $errors[$name] = self::convertToWindows1251((string) $messages);
            }
        }

        return $errors;
    }

    /**
     * Карта имён правил и классов валидаторов.
     *
     * Старые псевдонимы numerical и required сохранены.
     * Метод можно переопределить в наследнике для подключения собственных правил.
     */
    public function validators(): array
    {
        return [
            'boolean' => BooleanValidator::class,
            'recaptcha' => RecaptchaValidator::class,
            'email' => EmailValidator::class,
            'ip' => IpValidator::class,
            'length' => LengthValidator::class,
            'regex' => RegexValidator::class,
            'numerical' => NumericValidator::class,
            'numeric' => NumericValidator::class,
            'phone' => PhoneValidator::class,
            'required' => RequiredValidator::class,
            'url' => UrlValidator::class,
        ];
    }

    /**
     * Поддерживает новые валидаторы, старые классы Lema и строковые псевдонимы
     * в пользовательских переопределениях validators().
     */
    protected function runValidator($definition, string $ruleName, string $field, $value, array $params): array
    {
        if (is_string($definition) && class_exists($definition)) {
            if (is_a($definition, LegacyFieldValidator::class, true)) {
                if ($ruleName === 'recaptcha' && $this->recaptchaVerifier !== null) {
                    $params['verifier'] = $this->recaptchaVerifier;
                }

                /** @var LegacyFieldValidator $validator */
                $validator = new $definition($field, $value, $params);

                // Старый базовый валидатор выполнял проверку в конструкторе.
                return [$validator->isValid(), $validator->getError(), $validator->showAllErrors()];
            }

            if (is_a($definition, Validator::class, true)) {
                if ($ruleName === 'recaptcha' && $this->recaptchaVerifier !== null) {
                    $params['verify'] = $this->recaptchaVerifier;
                }

                /** @var Validator $validator */
                $validator = new $definition($field, $value, $params);
                $valid = $validator->validate();

                return [$valid, $validator->getError(), $validator->showAllErrors()];
            }

            // Пользовательский класс может реализовать старый контракт без наследования.
            $validator = new $definition($field, $value, $params);
            if (is_callable([$validator, 'validate'])) {
                $valid = (bool) $validator->validate();
                $message = is_callable([$validator, 'getError']) ? $validator->getError() : null;
                $showAll = is_callable([$validator, 'showAllErrors']) ? (bool) $validator->showAllErrors() : false;

                return [$valid, $message, $showAll];
            }

            throw new InvalidArgumentException('Класс валидатора не реализует метод validate(): ' . $definition);
        }

        // Совместимость с пользовательскими картами, возвращающими псевдонимы-строки.
        $alias = is_string($definition) ? strtolower($definition) : $ruleName;
        [$valid, $message] = $this->validateAlias($alias, $field, $value, $params);

        return [$valid, $message, (bool) ($params['showAllErrors'] ?? $params['show_all_errors'] ?? false)];
    }

    protected function validateAlias(string $type, string $field, $value, array $params): array
    {
        $map = [
            'boolean' => BooleanValidator::class,
            'recaptcha' => RecaptchaValidator::class,
            'email' => EmailValidator::class,
            'ip' => IpValidator::class,
            'length' => LengthValidator::class,
            'regex' => RegexValidator::class,
            'numeric' => NumericValidator::class,
            'numerical' => NumericValidator::class,
            'phone' => PhoneValidator::class,
            'required' => RequiredValidator::class,
            'url' => UrlValidator::class,
        ];

        if (!isset($map[$type])) {
            // Неизвестное правило, как и в старой библиотеке, пропускается.
            return [true, null];
        }

        if ($type === 'recaptcha' && $this->recaptchaVerifier !== null) {
            $params['verify'] = $this->recaptchaVerifier;
        }

        $class = $map[$type];
        $validator = new $class($field, $value, $params);
        $valid = $validator->validate();

        return [$valid, $validator->getError()];
    }

    protected function formatMessage($message, string $field, array $params): string
    {
        $message = is_scalar($message) ? (string) $message : ('Поле ' . $field . ' заполнено неверно');
        $replace = ['{name}' => $field];

        foreach ($params as $name => $value) {
            if (is_scalar($value)) {
                $replace['{' . $name . '}'] = (string) $value;
            }
        }

        return strtr($message, $replace);
    }

    protected function setError($name, $error, $showAllErrors = false): void
    {
        if (!array_key_exists($name, $this->errors)) {
            $this->errors[$name] = $error;
            return;
        }

        if (!$showAllErrors) {
            return;
        }

        if (!is_array($this->errors[$name])) {
            $this->errors[$name] = [$this->errors[$name]];
        }

        $this->errors[$name][] = $error;
    }

    private static function convertToWindows1251(string $value): string
    {
        $converted = iconv('UTF-8', 'Windows-1251//TRANSLIT', $value);

        return $converted === false ? '' : $converted;
    }
}
