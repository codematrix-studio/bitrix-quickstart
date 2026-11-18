<?php

declare(strict_types=1);

namespace Project\Support;

/**
 * Validates email addresses using PHP's built-in validation filter.
 *
 * This class does not verify DNS records or mailbox ownership.
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
