<?php

declare(strict_types=1);

namespace Project\Support;

/** Формы русского существительного в зависимости от целого числа. */
final class RussianPluralizer
{
    public static function word($number, array $forms)
    {
        if (!isset($forms[0], $forms[1], $forms[2]) || !is_numeric($number)) {
            return false;
        }

        $number = abs((int) $number);
        $lastTwo = $number % 100;
        $last = $number % 10;

        if ($last === 1 && $lastTwo !== 11) {
            return $forms[0];
        }

        if ($last >= 2 && $last <= 4 && ($lastTwo < 10 || $lastTwo > 20)) {
            return $forms[1];
        }

        return $forms[2];
    }

    public static function withNumber($number, array $forms, $separator = ' ')
    {
        return $number . $separator . self::word($number, $forms);
    }
}
