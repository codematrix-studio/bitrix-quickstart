<?php

/**
 * Optional compatibility bridge for the legacy global generate_pass() function.
 *
 * Include this file after Composer autoloading when migrating old code.
 */

if (!function_exists('generate_pass')) {
    function generate_pass($number)
    {
        return \Project\Support\PasswordGenerator::generate((int) $number);
    }
}
