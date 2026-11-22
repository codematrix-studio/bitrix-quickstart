<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * Базовая шапка основного шаблона.
 *
 * Подключение специфичных для сайта библиотек и ресурсов
 * выполняется в самом проекте, а не в стартовой заготовке.
 */

\Bitrix\Main\Page\Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/template_styles.css");
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialcharsbx(LANGUAGE_ID) ?>">
<head>
    <meta charset="<?= htmlspecialcharsbx(SITE_CHARSET) ?>">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?php $APPLICATION->ShowTitle(); ?></title>

    <?php
    // Мета-теги, заданные средствами Битрикс.
    $APPLICATION->ShowMeta("robots", false, false);
    $APPLICATION->ShowMeta("keywords", false, false);
    $APPLICATION->ShowMeta("description", false, false);

    // Канонический адрес текущей страницы.
    $APPLICATION->ShowLink("canonical", null, false);

    // Стили и скрипты, зарегистрированные средствами Битрикс.
    $APPLICATION->ShowHead();
    ?>
</head>
<body>
<div id="panel"><?php $APPLICATION->ShowPanel(); ?></div>
