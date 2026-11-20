<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * @var CBitrixComponent $this Текущий экземпляр компонента.
 * @var array<string, mixed> $arParams Параметры компонента.
 * @var array{
 *     FOLDER: string,
 *     URL_TEMPLATES: array{list: string, detail: string},
 *     VARIABLES: array<string, int|string>,
 *     ALIASES: array<string, string>
 * } $arResult Данные маршрутизации для дочерних шаблонов.
 * @global CMain $APPLICATION Объект текущего приложения Битрикс.
 */
global $APPLICATION;

if (!is_object($APPLICATION) || !method_exists($APPLICATION, 'GetCurPage')) {
    throw new \\RuntimeException('Не удалось получить текущую страницу Bitrix.');
}

$componentVariables = ['ELEMENT_ID'];
$defaultUrlTemplates = [
    'list' => '',
    'detail' => '#ELEMENT_ID#/',
];

$rawAliases = (array) ($arParams['VARIABLE_ALIASES'] ?? []);
$elementAlias = (string) ($rawAliases['ELEMENT_ID'] ?? 'ELEMENT_ID');

if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $elementAlias)) {
    $elementAlias = 'ELEMENT_ID';
}

$variableAliases = CComponentEngine::MakeComponentVariableAliases(
    [],
    ['ELEMENT_ID' => $elementAlias]
);

$variables = [];
$componentPage = 'list';
$sefMode = ($arParams['SEF_MODE'] ?? 'N') === 'Y';

$notFound = static function (): void {
    if (!defined('ERROR_404')) {
        define('ERROR_404', 'Y');
    }

    if (class_exists('CHTTP')) {
        \\CHTTP::SetStatus('404 Not Found');
    }

    ShowError('Страница не найдена.');
};

if ($sefMode) {
    $folder = '/' . trim((string) ($arParams['SEF_FOLDER'] ?? '/'), '/') . '/';
    if ($folder === '//') {
        $folder = '/';
    }

    $configuredTemplates = (array) ($arParams['SEF_URL_TEMPLATES'] ?? []);
    $urlTemplates = [];

    foreach ($defaultUrlTemplates as $page => $defaultTemplate) {
        $candidate = $configuredTemplates[$page] ?? $defaultTemplate;
        $urlTemplates[$page] = is_string($candidate) ? $candidate : $defaultTemplate;
    }

    $parsedPage = CComponentEngine::ParseComponentPath($folder, $urlTemplates, $variables);

    if (!is_string($parsedPage) || !array_key_exists($parsedPage, $urlTemplates)) {
        $notFound();

        return;
    }

    $componentPage = $parsedPage;

    CComponentEngine::InitComponentVariables(
        $componentPage,
        $componentVariables,
        $variableAliases,
        $variables
    );
} else {
    CComponentEngine::InitComponentVariables(
        false,
        $componentVariables,
        $variableAliases,
        $variables
    );

    $currentPage = (string) $APPLICATION->GetCurPage(false);
    $urlTemplates = [
        'list' => $currentPage,
        'detail' => $currentPage . '?' . rawurlencode($elementAlias) . '=#ELEMENT_ID#',
    ];

    $rawElementId = $variables['ELEMENT_ID'] ?? null;

    if ($rawElementId !== null && $rawElementId !== '') {
        $elementId = filter_var(
            $rawElementId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($elementId === false) {
            $notFound();

            return;
        }

        $variables['ELEMENT_ID'] = $elementId;
        $componentPage = 'detail';
    }
}

if ($componentPage === 'detail') {
    $elementId = filter_var(
        $variables['ELEMENT_ID'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($elementId === false) {
        $notFound();

        return;
    }

    $variables['ELEMENT_ID'] = $elementId;
}

$arResult = [
    'FOLDER' => $sefMode ? $folder : '',
    'URL_TEMPLATES' => $urlTemplates,
    'VARIABLES' => $variables,
    'ALIASES' => $variableAliases,
];

// Кеширование данных списка и элемента выполняйте в соответствующих дочерних компонентах.
$this->IncludeComponentTemplate($componentPage);
