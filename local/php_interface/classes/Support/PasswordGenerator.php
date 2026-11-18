<?php

declare(strict_types=1);

namespace Project\Support;

use InvalidArgumentException;

/**
 * Generates passwords using a cryptographically secure random source.
 */
final class PasswordGenerator
{
    public const DEFAULT_ALPHABET = 'abcdefghijklm noprstuvxyzABCDEFGHIJKLM NOPRSTUVXYZ1234567890.,()[]!?&^%@*$<>/|+-{}\`~';

    public static function generate(int $length, string $alphabet = self::DEFAULT_ALPHABET): string
    {
        if ($length <= 0) {
            return '';
        }

        if ($alphabet === '') {
            throw new InvalidArgumentException('The password alphabet cannot be empty.');
        }

        $alphabetLength = strlen($alphabet);
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, $alphabetLength - 1)];
        }

        return $password;
    }
}
