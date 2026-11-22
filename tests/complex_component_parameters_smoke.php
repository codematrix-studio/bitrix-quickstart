<?php

declare(strict_types=1);

define('B_PROLOG_INCLUDED', true);

require_once __DIR__ . '/../templates/components/complex/.parameters.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(isset($arComponentParameters['GROUPS']), 'Описание групп параметров должно присутствовать.');
$assert(isset($arComponentParameters['PARAMETERS']), 'Описание параметров должно находиться в PARAMETERS.');
$assert(
    isset($arComponentParameters['PARAMETERS']['VARIABLE_ALIASES']['ELEMENT_ID']),
    'Псевдоним ELEMENT_ID должен быть доступен в настройках компонента.'
);
$assert(
    isset($arComponentParameters['PARAMETERS']['SEF_MODE']['list']),
    'Шаблон маршрута list должен быть доступен в настройках ЧПУ.'
);
$assert(
    isset($arComponentParameters['PARAMETERS']['SEF_MODE']['detail']),
    'Шаблон маршрута detail должен быть доступен в настройках ЧПУ.'
);
$assert(
    $arComponentParameters['PARAMETERS']['SEF_MODE']['detail']['DEFAULT'] === '#ELEMENT_ID#/',
    'Для детального маршрута должен сохраняться шаблон URL по умолчанию.'
);
$assert(
    !array_key_exists('VARIABLE_ALIASES', $arComponentParameters)
        && !array_key_exists('SEF_MODE', $arComponentParameters),
    'Специальные параметры не должны находиться вне PARAMETERS.'
);

echo "Complex component parameter descriptor smoke test: OK\\n";
