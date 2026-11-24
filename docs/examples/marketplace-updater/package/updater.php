<?php

/**
 * Этот файл выполняется SiteUpdate до копирования файлов текущего пакета.
 * Не подключайте здесь классы, которые добавляются этим же обновлением.
 */

if (!IsModuleInstalled("vendor.advancedmodule")) {
    return;
}

$updater->CopyFiles("install/classes", "modules/vendor.advancedmodule/classes");
