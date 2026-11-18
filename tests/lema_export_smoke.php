<?php

declare(strict_types=1);

// Минимальный PSR-4 автозагрузчик для автономного запуска без vendor/autoload.php.
spl_autoload_register(static function (string $class): void {
    $prefix = 'Project\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/../local/php_interface/classes/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

function exportExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$tempDirectory = sys_get_temp_dir() . '/codematrix-export-' . bin2hex(random_bytes(5));
$cache = new Project\Support\JsonFileCache($tempDirectory);

$cacheData = [
    'generatedAt' => 123456,
    'products' => [10 => ['ID' => 10, 'NAME' => 'Тест']],
    'sections' => [5 => ['name' => 'Раздел']],
];
$cache->put('catalog', $cacheData);
exportExpect($cache->get('catalog') === $cacheData, 'JSON cache must preserve arrays.');
exportExpect($cache->isFresh('catalog', 60), 'Newly written cache must be fresh.');

file_put_contents($tempDirectory . '/broken.json', '{broken');
exportExpect($cache->get('broken') === null, 'Invalid JSON cache must be treated as a cache miss.');

$invalidKeyRejected = false;
try {
    $cache->get('../outside');
} catch (InvalidArgumentException $exception) {
    $invalidKeyRejected = true;
}
exportExpect($invalidKeyRejected, 'Cache keys must not allow directory traversal.');

$escaped = Project\Export\XmlEscaper::text("A & B <tag> \"quoted\" 'text' \x01");
exportExpect(str_contains($escaped, 'A &amp; B &lt;tag&gt;'), 'XML text must escape markup characters.');
exportExpect(!str_contains($escaped, "\x01"), 'XML text must remove invalid XML control characters.');

$urls = new Project\Support\UrlGenerator('https://shop.example.org');
$renderer = new Project\Export\YmlCatalogRenderer($urls, [
    'shopName' => 'Магазин & компания',
    'companyName' => 'ООО "Пример"',
]);

$xml = $renderer->render(
    [
        5 => ['name' => 'Товары & аксессуары'],
        6 => ['name' => 'Подраздел', 'parentId' => 5],
    ],
    [
        10 => [
            'ID' => 10,
            'ACTIVE' => 'Y',
            'CATALOG_AVAILABLE' => 'Y',
            'DETAIL_PAGE_URL' => '/catalog/item/?from=yml',
            'PREVIEW_PICTURE' => '/upload/item.jpg',
            'NAME' => 'Кружка <белая>',
            'PRICE' => 0,
            'COLOR' => 'Синий & белый',
            'IBLOCK_SECTION_ID' => 6,
            'DETAIL_TEXT' => '<p>Описание &amp; характеристики</p>',
        ],
    ],
    [
        'fields' => ['price' => 'PRICE'],
        'params' => [
            [0 => 'Цвет', 1 => 'COLOR', 'unit' => 'шт.'],
        ],
    ],
    1700000000
);

exportExpect(str_contains($xml, '<name>Магазин &amp; компания</name>'), 'Shop name must be XML escaped.');
exportExpect(str_contains($xml, '<name>Кружка &lt;белая&gt;</name>'), 'Product name must be XML escaped.');
exportExpect(str_contains($xml, '<price>0</price>'), 'Zero-valued product fields must not be omitted.');
exportExpect(str_contains($xml, '<param name="Цвет" unit="шт.">Синий &amp; белый</param>'), 'YML params and attributes must be escaped.');
exportExpect(str_contains($xml, '<description>Описание &amp; характеристики</description>'), 'Product description must be plain text and XML escaped.');
exportExpect(str_contains($xml, '<category id="6" parentId="5">Подраздел</category>'), 'Section hierarchy must be preserved.');
exportExpect(str_contains($xml, 'https://shop.example.org/catalog/item/?from=yml'), 'Relative product URLs must use the trusted site URL.');
exportExpect(str_contains($xml, 'https://shop.example.org/upload/item.jpg'), 'Relative image URLs must use the trusted site URL.');
exportExpect(str_contains($xml, 'available="true"'), 'Available products must retain the legacy availability rule.');

$externalUrlRejected = false;
try {
    $renderer->render([], [
        11 => ['ID' => 11, 'DETAIL_PAGE_URL' => 'https://attacker.example/item/'],
    ]);
} catch (InvalidArgumentException $exception) {
    $externalUrlRejected = true;
}
exportExpect($externalUrlRejected, 'YML renderer must reject product URLs from another host.');

$invalidTagRejected = false;
try {
    $renderer->render([], [], ['fields' => ['price><script' => 'PRICE']]);
} catch (InvalidArgumentException $exception) {
    $invalidTagRejected = true;
}
exportExpect($invalidTagRejected, 'YML renderer must reject untrusted XML tag names.');

class ExportSmokeCFile
{
    public static function GetPath(int $fileId): string
    {
        return '/upload/file' . $fileId . '.jpg';
    }
}
if (!class_exists('CFile')) {
    class_alias(ExportSmokeCFile::class, 'CFile');
}

// Bitrix-адаптер можно проверять без ядра через внедрённые репозитории.
$exportDirectory = $tempDirectory . '/bitrix-export';
$elementRepository = new Project\Bitrix\IBlock\ElementRepository(
    static fn (): bool => true,
    null,
    static fn (...$arguments): array => [[
        'ID' => 10,
        'IBLOCK_ID' => 12,
        'ACTIVE' => 'Y',
        'NAME' => 'Тестовый товар',
        'PREVIEW_PICTURE' => 7,
        'PREVIEW_PAGE_URL' => '/preview/test/',
        'DETAIL_PAGE_URL' => '/catalog/test/',
        'PREVIEW_TEXT' => '<p>Краткий &amp; текст</p>',
        'DETAIL_TEXT' => '<div>Полное описание</div>',
        'CATALOG_AVAILABLE' => 'Y',
    ]]
);
$sectionRepository = new Project\Bitrix\IBlock\SectionRepository(
    static fn (): bool => true,
    null,
    static function ($order, $filter) {
        if (isset($filter['ID'])) {
            return [[
                'ID' => 5,
                'NAME' => 'Корневой раздел',
                'LEFT_MARGIN' => 1,
                'RIGHT_MARGIN' => 4,
            ]];
        }

        return [[
            'ID' => 6,
            'NAME' => 'Дочерний раздел',
            'IBLOCK_SECTION_ID' => 5,
        ]];
    }
);

$exporterConfig = [
    'sectionIds' => [5],
    'productIds' => [10],
    'additionalData' => ['source' => 'test'],
    'cacheTtl' => 3600,
    'cacheKey' => 'catalog-export-test',
];
$exporter = new Project\Export\BitrixCatalogExporter(
    12,
    $urls,
    $exportDirectory,
    $exporterConfig,
    $elementRepository,
    $sectionRepository
);
$loaded = $exporter->loadData([], true);
exportExpect(isset($loaded['products'][10]), 'Bitrix exporter must return products keyed by ID.');
exportExpect(isset($loaded['sections'][6]), 'Bitrix exporter must include descendants of selected sections.');
exportExpect($loaded['sections'][6]['parentId'] === 5, 'Bitrix exporter must preserve section parent IDs.');
exportExpect(
    $loaded['products'][10]['PREVIEW_PICTURE'] === 'https://shop.example.org/upload/file7.jpg',
    'Bitrix exporter must resolve image IDs to trusted absolute URLs.'
);
exportExpect(
    $loaded['products'][10]['PREVIEW_TEXT'] === 'Краткий & текст',
    'Bitrix exporter must convert preview HTML to plain text.'
);
exportExpect(
    $loaded['products'][10]['PREVIEW_PAGE_URL'] === 'https://shop.example.org/preview/test/',
    'Bitrix exporter must resolve preview page URLs against the trusted base URL.'
);

// Повторная загрузка с теми же параметрами использует кеш и не вызывает репозитории.
$cachedExporter = new Project\Export\BitrixCatalogExporter(
    12,
    $urls,
    $exportDirectory,
    $exporterConfig,
    new Project\Bitrix\IBlock\ElementRepository(
        static fn (): bool => true,
        null,
        static function () {
            throw new RuntimeException('Cache should prevent the product query.');
        }
    ),
    new Project\Bitrix\IBlock\SectionRepository(
        static fn (): bool => true,
        null,
        static function () {
            throw new RuntimeException('Cache should prevent the section query.');
        }
    )
);
$cachedData = $cachedExporter->loadData([], true);
exportExpect($cachedData === $loaded, 'Bitrix exporter must restore the complete catalog from cache.');

// Совместимые имена Lema подключаются отдельно и требуют доверенный serverUrl.
$documentRoot = $tempDirectory . '/document-root';
mkdir($documentRoot, 0750, true);
$_SERVER['DOCUMENT_ROOT'] = $documentRoot;

require_once __DIR__ . '/../local/php_interface/legacy/Lema/Base/XmlExport.php';
require_once __DIR__ . '/../local/php_interface/legacy/Lema/Seo/YmlExport.php';

$legacyYml = new \Lema\Seo\YmlExport(12, [
    'serverUrl' => 'https://shop.example.org',
    'storageDir' => '/bitrix/catalog_export/export',
    'sectionIds' => [5],
]);
exportExpect($legacyYml instanceof \Lema\Base\XmlExport, 'Legacy YmlExport must extend the legacy XmlExport adapter.');
exportExpect($legacyYml->setNeedUpdate(true) === true, 'Legacy setNeedUpdate() must retain its boolean return contract.');

$legacyBase = new \Lema\Base\XmlExport(12, [
    'serverUrl' => 'https://shop.example.org',
    'storageDir' => '/bitrix/catalog_export/base',
]);
$baseShowDataRejected = false;
try {
    $legacyBase->showData();
} catch (LogicException $exception) {
    $baseShowDataRejected = true;
}
exportExpect($baseShowDataRejected, 'Base XmlExport must explicitly reject an undefined XML format.');

class ExportLegacyCustomYml extends \Lema\Seo\YmlExport
{
    public function loadAdditionalData(array $additionalData = []): void
    {
        $this->additionalData = ['loadedByLegacyHook' => true];
    }

    protected function loadSections(): array
    {
        return [];
    }

    protected function loadProducts(array $params = []): array
    {
        return [];
    }
}

$legacyHookExport = new ExportLegacyCustomYml(12, [
    'serverUrl' => 'https://shop.example.org',
    'storageDir' => '/bitrix/catalog_export/hook',
    'cacheKey' => 'legacy-hook-test',
]);
$legacyHookExport->loadData();
exportExpect(
    ($legacyHookExport->getAdditionalData()['loadedByLegacyHook'] ?? false) === true,
    'Legacy subclasses overriding loadAdditionalData() must retain the refresh hook.'
);

$unsafeStorageRejected = false;
try {
    new \Lema\Base\XmlExport(12, [
        'serverUrl' => 'https://shop.example.org',
        'storageDir' => '/../../outside',
    ]);
} catch (InvalidArgumentException $exception) {
    $unsafeStorageRejected = true;
}
exportExpect($unsafeStorageRejected, 'Legacy storageDir must reject directory traversal.');

foreach (glob($exportDirectory . '/*') ?: [] as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}
foreach (glob($exportDirectory . '/.*') ?: [] as $file) {
    if (is_file($file) && basename($file) !== '.' && basename($file) !== '..') {
        unlink($file);
    }
}
rmdir($exportDirectory);

$legacyStorageRoot = $documentRoot . '/bitrix/catalog_export';
foreach (glob($legacyStorageRoot . '/*') ?: [] as $directory) {
    if (is_dir($directory)) {
        foreach (array_merge(glob($directory . '/*') ?: [], glob($directory . '/.*') ?: []) as $file) {
            if (is_file($file) && basename($file) !== '.' && basename($file) !== '..') {
                unlink($file);
            }
        }
        rmdir($directory);
    }
}
if (is_dir($legacyStorageRoot)) {
    rmdir($legacyStorageRoot);
}
if (is_dir($documentRoot . '/bitrix')) {
    rmdir($documentRoot . '/bitrix');
}
rmdir($documentRoot);

foreach (glob($tempDirectory . '/*') ?: [] as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}
foreach (glob($tempDirectory . '/.*') ?: [] as $file) {
    if (is_file($file) && basename($file) !== '.' && basename($file) !== '..') {
        unlink($file);
    }
}
rmdir($tempDirectory);

echo "Проверки экспорта XML/YML и JSON-кеша пройдены.\n";
