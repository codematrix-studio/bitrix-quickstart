<?php

declare(strict_types=1);

require_once __DIR__ . '/../local/php_interface/classes/Support/HtmlEscaper.php';
require_once __DIR__ . '/../local/php_interface/classes/Support/RussianPluralizer.php';
require_once __DIR__ . '/../local/php_interface/classes/Support/JsonEncoder.php';
require_once __DIR__ . '/../local/php_interface/classes/Support/CharsetConverter.php';
require_once __DIR__ . '/../local/php_interface/classes/Support/UrlGenerator.php';
require_once __DIR__ . '/../local/php_interface/classes/Bitrix/IBlock/PropertyAccessor.php';
require_once __DIR__ . '/../local/php_interface/classes/Bitrix/IBlock/ImageWatermarker.php';
require_once __DIR__ . '/../local/php_interface/legacy/CodeMatrix/Legacy/Common/Helper.php';

function helperExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$forms = ['товар', 'товара', 'товаров'];
helperExpect(\CodeMatrix\Legacy\Common\Helper::pluralize(1, $forms) === 'товар', 'Pluralizer must return singular form.');
helperExpect(\CodeMatrix\Legacy\Common\Helper::pluralize(2, $forms) === 'товара', 'Pluralizer must return paucal form.');
helperExpect(\CodeMatrix\Legacy\Common\Helper::pluralize(5, $forms) === 'товаров', 'Pluralizer must return plural form.');
helperExpect(\CodeMatrix\Legacy\Common\Helper::pluralize(11, $forms) === 'товаров', 'Pluralizer must handle 11–14.');
helperExpect(\CodeMatrix\Legacy\Common\Helper::pluralize(21, $forms) === 'товар', 'Pluralizer must handle numbers ending in 1.');
helperExpect(\CodeMatrix\Legacy\Common\Helper::pluralizeN(3, $forms) === '3 товара', 'pluralizeN must preserve the old output format.');
helperExpect(\CodeMatrix\Legacy\Common\Helper::pluralize(2, ['one']) === false, 'Pluralizer must reject incomplete forms.');

$encoded = \CodeMatrix\Legacy\Common\Helper::enc('Кавычки " и <тег>');
helperExpect(str_contains($encoded, '&lt;тег&gt;'), 'HTML helper must escape markup.');
helperExpect(\CodeMatrix\Legacy\Common\Helper::getJson(['ok' => true]) === '{"ok":true}', 'JSON helper must return encoded JSON.');

$cp1251 = \CodeMatrix\Legacy\Common\Helper::utf8ToCP1251('Привет');
helperExpect(is_string($cp1251) && $cp1251 !== '', 'Charset helper must convert UTF-8 to Windows-1251.');
helperExpect(\Project\Support\CharsetConverter::fromWindows1251($cp1251) === 'Привет', 'Charset conversion must round-trip Russian text.');

$data = [
    'PROPERTIES' => [
        'PRICE' => ['VALUE' => '100'],
        'EMPTY' => ['VALUE' => ''],
    ],
];
helperExpect(\CodeMatrix\Legacy\Common\Helper::propValue('PRICE', $data) === '100', 'Property helper must return VALUE.');
helperExpect(\CodeMatrix\Legacy\Common\Helper::prop('PRICE', $data)['VALUE'] === '100', 'Property helper must return property data.');
helperExpect(\CodeMatrix\Legacy\Common\Helper::propFilled('PRICE', $data), 'Property helper must detect a filled property.');
helperExpect(\CodeMatrix\Legacy\Common\Helper::propEmpty('EMPTY', $data), 'Property helper must detect an empty property.');
helperExpect(\CodeMatrix\Legacy\Common\Helper::escPropValue('PRICE', $data) === '100', 'Escaped property helper must encode values.');

\CodeMatrix\Legacy\Common\Helper::setBaseUrl('https://shop.example.org/');
helperExpect(\CodeMatrix\Legacy\Common\Helper::getFullUrl('/catalog/') === 'https://shop.example.org/catalog/', 'Full URL helper must use the configured trusted host.');

$rejectedHost = false;
try {
    \CodeMatrix\Legacy\Common\Helper::getFullUrl('//attacker.example/path');
} catch (InvalidArgumentException $exception) {
    $rejectedHost = true;
}
helperExpect($rejectedHost, 'Full URL helper must reject protocol-relative external URLs.');

helperExpect(\CodeMatrix\Legacy\Common\Helper::getImageWatermark([], []) === false, 'Watermark helper must reject incomplete image data.');

echo "Проверки Common Helper пройдены.\n";
