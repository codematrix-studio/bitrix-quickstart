<?php

namespace Ns\Bitrix\Helper\IBlock;

use Project\Support\HtmlEscaper;

/**
 * Совместимый адаптер старого Ns\Bitrix\Helper\IBlock\Validator.
 *
 * Сохраняет контракт email() и xss() для кода, который переносится со старой
 * библиотеки. Для нового кода предпочтительнее использовать отдельные классы
 * Project\Support и экранировать данные непосредственно перед выводом.
 */
class Validator
{
    private HtmlEscaper $htmlEscaper;

    public function __construct(?HtmlEscaper $htmlEscaper = null)
    {
        $this->htmlEscaper = $htmlEscaper ?? new HtmlEscaper();
    }

    /**
     * Проверяет email и возвращает исходную/очищенную строку либо false.
     *
     * Важно: при $xss=true сначала выполняется старое преобразование xss(),
     * поэтому возвращается HTML-экранированная строка. При $xss=false значение
     * не обрезается и не изменяется до filter_var().
     *
     * @return string|false
     */
    public function email($email = '', $xss = true)
    {
        if ($xss) {
            $email = $this->xss($email);
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
    }

    /**
     * Обрезает внешние пробелы и экранирует значение для HTML.
     *
     * Сигнатура намеренно не типизирована: это legacy API, где scalar-значения
     * неявно приводились PHP к строке функцией trim().
     */
    public function xss($value = '')
    {
        $encoding = ini_get('default_charset') ?: 'UTF-8';

        return $this->htmlEscaper->escape(
            trim($value),
            ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401,
            $encoding
        );
    }
}
