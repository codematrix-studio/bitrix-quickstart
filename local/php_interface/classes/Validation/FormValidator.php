<?php

declare(strict_types=1);

namespace Project\Validation;

use Closure;
use InvalidArgumentException;

/**
 * Проверяет набор полей по правилам, совместимым с форматом lema.lib.
 *
 * Формат правила: [ "email, phone", "email", ["message" => "..."] ].
 * Класс не очищает HTML и не экранирует значения: это разные задачи.
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
     * Задаёт проверяющую функцию для reCAPTCHA. Без неё проверка всегда закрывается отказом.
     */
    public function setRecaptchaVerifier(?callable $verifier): static
    {
        $this->recaptchaVerifier = $verifier === null ? null : Closure::fromCallable($verifier);

        return $this;
    }

    public function validate(): bool
    {
        // Повторный вызов validate() всегда пересчитывает ошибки с нуля.
        $this->errors = [];

        foreach ($this->rules as $rule) {
            if (!is_array($rule) || !isset($rule[0], $rule[1]) || !is_string($rule[0]) || !is_string($rule[1])) {
                continue;
            }

            $params = isset($rule[2]) && is_array($rule[2]) ? $rule[2] : [];
            $fieldNames = preg_split('~\\s*,\\s*~u', $rule[0], -1, PREG_SPLIT_NO_EMPTY);

            if ($fieldNames === false) {
                continue;
            }

            foreach ($fieldNames as $fieldName) {
                $value = $this->fields[$fieldName] ?? null;
                [$valid, $message] = $this->validateValue($rule[1], $fieldName, $value, $params);

                if (!$valid) {
                    $this->setError(
                        $fieldName,
                        $message,
                        (bool) ($params['showAllErrors'] ?? false)
                    );
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

    protected function checkValue($name, $validator): bool
    {
        if (is_callable($validator)) {
            $result = $validator();
            return (bool) $result;
        }

        return false;
    }

    protected function setError($name, $error, $showAllErrors = false): void
    {
        if (!isset($this->errors[$name])) {
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

    /**
     * Возвращает список поддерживаемых имён правил.
     * Старые псевдонимы numerical и required сохранены.
     */
    public function validators(): array
    {
        return [
            'boolean' => 'boolean',
            'recaptcha' => 'recaptcha',
            'email' => 'email',
            'ip' => 'ip',
            'length' => 'length',
            'regex' => 'regex',
            'numerical' => 'numeric',
            'numeric' => 'numeric',
            'phone' => 'phone',
            'required' => 'required',
            'url' => 'url',
        ];
    }

    protected function validateValue(string $type, string $field, $value, array $params): array
    {
        $messages = [
            'boolean' => 'Поле {name} должно быть булевым',
            'recaptcha' => 'Не удалось проверить проверочный код',
            'email' => 'Неверный формат E-mail',
            'ip' => 'Неверный формат IP-адреса',
            'length' => 'Поле {name} должно содержать от {min} до {max} символов',
            'regex' => 'Неверный формат {name}',
            'numeric' => 'Поле {name} должно быть числом',
            'phone' => 'Неверный формат телефона',
            'required' => 'Введите {name}',
            'url' => 'Неверный формат URL',
        ];

        if (!array_key_exists($type, $this->validators())) {
            return [true, null];
        }

        // Старое имя правила numerical оставлено как псевдоним numeric.
        $type = $type === 'numerical' ? 'numeric' : $type;

        $valid = false;
        switch ($type) {
            case 'required':
                $valid = !($value === null || $value === '' || (is_array($value) && $value === []));
                break;

            case 'boolean':
                if (is_bool($value) || $value === 0 || $value === 1 || $value === '0' || $value === '1') {
                    $valid = true;
                } elseif (is_string($value)) {
                    $valid = in_array(strtolower(trim($value)), ['true', 'false', 'yes', 'no', 'on', 'off'], true);
                }
                break;

            case 'email':
                $valid = is_string($value) && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
                break;

            case 'ip':
                $valid = is_string($value) && filter_var($value, FILTER_VALIDATE_IP) !== false;
                break;

            case 'length':
                $length = self::unicodeLength(is_scalar($value) ? (string) $value : '');
                $min = isset($params['min']) ? max(0, (int) $params['min']) : null;
                $max = isset($params['max']) ? max(0, (int) $params['max']) : null;
                $valid = ($min === null || $length >= $min) && ($max === null || $length <= $max);
                break;

            case 'regex':
                $pattern = $params['pattern'] ?? null;
                if (is_string($pattern) && $pattern !== '' && is_string($value)) {
                    $result = @preg_match($pattern, $value);
                    $valid = $result === 1;
                }
                break;

            case 'numeric':
                $valid = is_scalar($value) && $value !== '' && is_numeric($value);
                break;

            case 'phone':
                $valid = is_string($value) && preg_match(
                    '~^\\s*(?:(?:\\+?\\s*?7)|(?:8))?\\s*[-(]?\\d{3}[)]?\\s*[-]?\\d{3}\\s*[-]?\\d{2}\\s*[-]?\\d{2}\\s*$~',
                    $value
                ) === 1;
                break;

            case 'url':
                $valid = is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false;
                break;

            case 'recaptcha':
                if ($this->recaptchaVerifier !== null && is_string($value) && $value !== '') {
                    $valid = (bool) ($this->recaptchaVerifier)($value, $params, $field);
                }
                break;
        }

        if ($valid) {
            return [true, null];
        }

        $message = $params['message'] ?? ($messages[$type] ?? 'Некорректное значение поля {name}');
        $replacements = ['{name}' => $field];

        foreach ($params as $name => $paramValue) {
            if (is_scalar($paramValue)) {
                $replacements['{' . $name . '}'] = (string) $paramValue;
            }
        }

        return [false, strtr((string) $message, $replacements)];
    }

    private static function unicodeLength(string $value): int
    {
        if (preg_match_all('/./us', $value, $matches) !== false) {
            return count($matches[0]);
        }

        return strlen($value);
    }

    private static function convertToWindows1251(string $value): string
    {
        $converted = iconv('UTF-8', 'Windows-1251//TRANSLIT', $value);

        return $converted === false ? '' : $converted;
    }
}
