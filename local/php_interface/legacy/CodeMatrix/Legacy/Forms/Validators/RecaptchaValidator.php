<?php

namespace CodeMatrix\Legacy\Forms\Validators;

/**
 * Без внедрённой функции-проверяющего валидатор всегда возвращает ошибку.
 * Старый вызов Google reCAPTCHA v1/v2 удалён как устаревший и небезопасный.
 */
class RecaptchaValidator extends Validator
{
    protected string $ruleName = 'recaptcha';
}
