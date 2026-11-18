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
