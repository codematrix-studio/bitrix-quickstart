<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * @var CBitrixComponent $this Текущий экземпляр компонента.
 * @var array<string, mixed> $arParams Параметры компонента.
 * @var array<string, mixed> $arResult Результат работы компонента.
 * @global CMain $APPLICATION Объект текущего приложения Битрикс.
 */
global $APPLICATION;

$defaultUrlTemplates = [
    'list' => '',
    'detail' => '#ELEMENT_ID#/',
];

$componentVariables = [
    'ELEMENT_ID',
];

$variables = [];
$variableAliases = [];
$urlTemplates = [];
$componentPage = 'list';

if (($arParams['SEF_MODE'] ?? 'N') === 'Y') {
    $urlTemplates = CComponentEngine::MakeComponentUrlTemplates(
        $defaultUrlTemplates,
        (array) ($arParams['SEF_URL_TEMPLATES'] ?? [])
    );

    $variableAliases = CComponentEngine::MakeComponentVariableAliases(
        [],
        (array) ($arParams['VARIABLE_ALIASES'] ?? [])
    );

    $componentPage = CComponentEngine::ParseComponentPath(
        (string) ($arParams['SEF_FOLDER'] ?? ''),
        $urlTemplates,
        $variables
    ) ?: 'list';

    CComponentEngine::InitComponentVariables(
        $componentPage,
        $componentVariables,
        $variableAliases,
        $variables
    );
} else {
    $variableAliases = CComponentEngine::MakeComponentVariableAliases(
        [],
        (array) ($arParams['VARIABLE_ALIASES'] ?? [])
    );

    CComponentEngine::InitComponentVariables(
        false,
        $componentVariables,
        $variableAliases,
        $variables
    );

    if ((int) ($variables['ELEMENT_ID'] ?? 0) > 0) {
        $componentPage = 'detail';
    }

    $currentPage = htmlspecialcharsbx($APPLICATION->GetCurPage());
    $elementParameter = $variableAliases['ELEMENT_ID'] ?? 'ELEMENT_ID';

    $urlTemplates = [
        'list' => $currentPage,
        'detail' => $currentPage . '?' . $elementParameter . '=#ELEMENT_ID#',
    ];
}

$arResult = [
    'FOLDER' => (string) ($arParams['SEF_FOLDER'] ?? ''),
    'URL_TEMPLATES' => $urlTemplates,
    'VARIABLES' => $variables,
    'ALIASES' => $variableAliases,
];

$this->IncludeComponentTemplate($componentPage);
