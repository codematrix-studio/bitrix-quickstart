<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Page\Asset;
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

    // Иконка сайта.
    ?>
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">

    <?php
    /**
     * ПРИМЕРЫ ПОДКЛЮЧЕНИЯ CSS.
     *
     * Ресурсы добавляются через Asset, а выводятся вызовом ShowHead().
     * Раскомментируйте нужные строки и проверьте пути в const.php.
     */

    // Стили текущего шаблона.
    Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/template_styles.css");

    // Bootstrap:
    // Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/css/bootstrap.css");
    // Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/css/bootstrap-theme.css");

    // Сторонние библиотеки:
    // Asset::getInstance()->addCss(PATH_BOWER_COMPONENTS . "/animate.css/animate.min.css");
    // Asset::getInstance()->addCss(PATH_BOWER_COMPONENTS . "/fancybox/dist/jquery.fancybox.min.css");

    // Общие стили проекта:
    // Asset::getInstance()->addCss(PATH_GLOBAL_CSS);
    // Asset::getInstance()->addCss(PATH_RESPONSIVE_CSS);

    /**
     * ПРИМЕРЫ ПОДКЛЮЧЕНИЯ JAVASCRIPT.
     */

    // jQuery из ядра Битрикс:
    // CJSCore::Init(array("jquery"));

    // Другие варианты идентификаторов jQuery, если они зарегистрированы в проекте:
    // CJSCore::Init(array("jquery2"));
    // CJSCore::Init(array("jquery_3"));

    // Сторонние библиотеки и скрипты проекта:
    // Asset::getInstance()->addJs(PATH_BOWER_COMPONENTS . "/fancybox/dist/jquery.fancybox.min.js");
    // Asset::getInstance()->addJs(PATH_GLOBAL_JS);
    // Asset::getInstance()->addJs(PATH_AJAX_JS);

    /**
     * Дополнительные строки в HEAD:
     *
     * Asset::getInstance()->addString('<meta name="theme-color" content="#ffffff">');
     */

    // Вывод зарегистрированных ресурсов и строк HEAD.
    $APPLICATION->ShowHead();
    ?>
</head>
<body>
<div id="panel"><?php $APPLICATION->ShowPanel(); ?></div>
