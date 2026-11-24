<?php

declare(strict_types=1);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$packageRoot = __DIR__ . '/../docs/examples/marketplace-updater/package';
$versionFile = $packageRoot . '/install/version.php';
$descriptionFile = $packageRoot . '/description.ru';
$updaterFile = $packageRoot . '/updater.php';
$markerFile = $packageRoot . '/install/classes/UpgradeMarker.php';

foreach ([$versionFile, $descriptionFile, $updaterFile, $markerFile] as $requiredFile) {
    $assert(is_file($requiredFile), 'Required updater package file is missing: ' . $requiredFile);
}

$arModuleVersion = [];
include $versionFile;

$assert(
    isset($arModuleVersion['VERSION'], $arModuleVersion['VERSION_DATE']),
    'Update package version metadata is incomplete.'
);
$assert(
    preg_match('/^\d+\.\d+\.\d+$/D', (string) $arModuleVersion['VERSION']) === 1,
    'Update package VERSION must use a three-part numeric version.'
);
$assert(
    preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', (string) $arModuleVersion['VERSION_DATE']) === 1,
    'Update package VERSION_DATE must use YYYY-MM-DD HH:MM:SS.'
);
$assert(trim((string) file_get_contents($descriptionFile)) !== '', 'Update description must not be empty.');

$updater = (string) file_get_contents($updaterFile);
$assert(
    strpos($updater, 'IsModuleInstalled("vendor.advancedmodule")') !== false,
    'Updater must skip sites where the module is not installed.'
);
$assert(
    strpos($updater, '$updater->CopyFiles("install/classes", "modules/vendor.advancedmodule/classes");') !== false,
    'Updater must use the documented CopyFiles call.'
);
$assert(
    strpos($updater, 'MigrationRunner') === false
        && strpos($updater, 'EventRegistry') === false
        && strpos($updater, 'AgentRegistry') === false
        && strpos($updater, 'Vendor\\AdvancedModule') === false,
    'Updater must not depend on runtime classes from the current package.'
);
$assert(
    !is_file($packageRoot . '/updater/index.php'),
    'Package must contain only one updater entry point.'
);

echo "Advanced Marketplace updater smoke test: OK\n";
