<?php

declare(strict_types=1);

namespace Project\Validation;

/**
 * Проверка формы и интеграция с почтовыми событиями/инфоблоками Битрикс.
 */
class AjaxForm extends FormValidator
{
    use AjaxFormActions;
}
