<?php

/**
 * Совместимый класс формы из lema.lib.
 * Подключать после Composer autoload.
 */

namespace Lema\Forms;

class Form extends \Project\Validation\FormValidator
{
    public function validators(): array
    {
        return [
            'boolean' => \\Lema\\Forms\\Validators\\BooleanValidator',
            'recaptcha' => \\Lema\\Forms\\Validators\\RecaptchaValidator',
            'email' => \\Lema\\Forms\\Validators\\EmailValidator',
            'ip' => \\Lema\\Forms\\Validators\\IpValidator',
            'length' => \\Lema\\Forms\\Validators\\LengthValidator',
            'regex' => \\Lema\\Forms\\Validators\\RegexValidator',
            'numerical' => \\Lema\\Forms\\Validators\\NumericValidator',
            'phone' => \\Lema\\Forms\\Validators\\PhoneValidator',
            'required' => \\Lema\\Forms\\Validators\\EmptyValidator',
            'url' => \\Lema\\Forms\\Validators\\UrlValidator',
        ];
    }
}
