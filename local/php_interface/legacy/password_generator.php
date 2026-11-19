<?php

/**
 * Необязательный адаптер совместимости со старой глобальной функцией generate_pass().
 *
 * При переносе старого кода подключайте этот файл после загрузки Composer autoloader.
 */

if (!function_exists('generate_pass')) {
    function generate_pass($number)
    {
        return \Project\Support\PasswordGenerator::generate((int) $number);
    }
}
