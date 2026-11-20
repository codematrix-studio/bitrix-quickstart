<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * @var array{ITEMS: list<array{NAME: string}>} $arResult Результат D7-компонента.
 */
?>
<div class="project-component">
    <?php foreach ($arResult['ITEMS'] as $item): ?>
        <div class="project-component__item">
            <?= htmlspecialcharsbx($item['NAME']) ?>
        </div>
    <?php endforeach; ?>
</div>
