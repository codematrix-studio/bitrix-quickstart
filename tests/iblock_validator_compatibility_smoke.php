<?php

declare(strict_types=1);

require_once __DIR__ . '/../local/php_interface/classes/Support/HtmlEscaper.php';
require_once __DIR__ . '/../local/php_interface/legacy/Ns/Bitrix/Helper/IBlock/Validator.php';

function iblockValidatorExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$validator = new \Ns\Bitrix\Helper\IBlock\Validator();

iblockValidatorExpect(
    $validator->email('  person@example.org  ') === 'person@example.org',
    'email() must trim and return the normalized address by default.'
);
iblockValidatorExpect(
    $validator->email('person@example.org', false) === 'person@example.org',
    'email() must validate a valid address when escaping is disabled.'
);
iblockValidatorExpect(
    $validator->email('  person@example.org  ', false) === false,
    'email(..., false) must not trim input before validation.'
);
iblockValidatorExpect(
    $validator->email('not-an-email') === false,
    'email() must return false for an invalid address.'
);
iblockValidatorExpect(
    $validator->xss('  <script>alert("x")</script>  ')
        === '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;',
    'xss() must trim and HTML-escape using the legacy default flags.'
);
iblockValidatorExpect(
    $validator->xss('  Привет  ') === 'Привет',
    'xss() must preserve UTF-8 text.'
);

echo "Проверки IBlock Validator пройдены.\n";
