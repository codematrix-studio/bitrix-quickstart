<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array<string, mixed> $arResult */
?>

<div class="example-component">
    <h2><?= htmlspecialcharsbx((string) ($arResult['TITLE'] ?? '')) ?></h2>

    <?php if (!empty($arResult['ITEMS'])): ?>
        <!-- Вывод подготовленных элементов компонента. -->
    <?php endif; ?>
</div>
