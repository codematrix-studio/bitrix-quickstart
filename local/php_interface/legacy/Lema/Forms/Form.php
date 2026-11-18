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
            'boolean' => Validators\BooleanValidator::class,
            'recaptcha' => Validators\RecaptchaValidator::class,
            'email' => Validators\EmailValidator::class,
            'ip' => Validators\IpValidator::class,
            'length' => Validators\LengthValidator::class,
            'regex' => Validators\RegexValidator::class,
            'numerical' => Validators\NumericValidator::class,
            'phone' => Validators\PhoneValidator::class,
            'required' => Validators\EmptyValidator::class,
            'url' => Validators\UrlValidator::class,
        ];
    }
}
