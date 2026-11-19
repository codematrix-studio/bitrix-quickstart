<?php

declare(strict_types=1);

namespace Project\Support;

/**
 * Проверяет адреса электронной почты с помощью встроенного фильтра PHP.
 *
 * Класс не проверяет DNS-записи и принадлежность почтового ящика.
 */
final class EmailValidator
{
    public function isValid(string $email): bool
    {
        $email = trim($email);

        return $email !== ''
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
