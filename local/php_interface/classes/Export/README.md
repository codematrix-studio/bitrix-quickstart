# Экспорт XML/YML и файловый кеш

В этом каталоге находятся классы экспорта, не привязанные к шаблонам конкретного сайта. Подготовка данных Битрикс, формирование YML и работа с кешем разделены.

## JsonFileCache

`Project\Support\JsonFileCache` — файловый кеш массивов в JSON.

```php
$cache = new \Project\Support\JsonFileCache('/var/www/site/upload/catalog-cache');

$cache->put('catalog', [
    'products' => [],
    'sections' => [],
]);

$data = $cache->get('catalog');
$isFresh = $cache->isFresh('catalog', 86400);
```

Особенности:

- ключи файлов ограничены буквами, цифрами, точкой, дефисом и подчёркиванием;
- `..`, слеши и другие варианты выхода из каталога запрещены;
- чтение и запись одного ключа синхронизируются через `flock()`;
- JSON записывается во временный файл, затем заменяет основной через `rename()`;
- некорректный или пустой JSON считается промахом кеша и возвращает `null`;
- ошибки записи и сериализации приводят к `RuntimeException`;
- время жизни кеша задаётся вызывающим классом через `isFresh()`.

Каталог кеша должен быть доступен на запись пользователю PHP. Не размещайте его внутри публичного каталога, если файлы кеша не должны быть доступны по HTTP.

## XmlEscaper

`Project\Export\XmlEscaper` предоставляет два метода:

- `text($value)` — экранирование XML-текста;
- `attribute($value)` — экранирование значения XML-атрибута.

Используется `ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE`, UTF-8. Недопустимые для XML 1.0 управляющие символы удаляются. Для массивов и объектов без `Stringable` выбрасывается `InvalidArgumentException`.

## YmlCatalogRenderer

`Project\Export\YmlCatalogRenderer` строит строку YML из уже подготовленных массивов. К ядру Битрикс и файловой системе не обращается.

```php
$urls = new \Project\Support\UrlGenerator('https://shop.example.org');

$renderer = new \Project\Export\YmlCatalogRenderer($urls, [
    'shopName' => 'Магазин',
    'companyName' => 'ООО Пример',
    'currencyId' => 'RUB',
    'currencyRate' => '1',
]);

$xml = $renderer->render(
    $sections,
    $products,
    [
        'fields' => [
            'price' => 'PRICE',
            'vendorCode' => 'XML_ID',
        ],
        'params' => [
            [0 => 'Цвет', 1 => 'COLOR', 'unit' => 'шт.'],
        ],
    ],
    time()
);
```

Параметры:

- `shopName`, `companyName` — название магазина и компании; если не заданы, используется host доверенного базового URL;
- `platform` — название платформы, по умолчанию `1C-Битрикс`;
- `currencyId`, `currencyRate` — валюта и курс, по умолчанию `RUB` и `1`;
- `fields` — соответствие имени XML-тега ключу в массиве товара;
- `params` — список дополнительных параметров товара в старом формате `[название, ключ_поля]`, необязательный `unit`;
- время формирования — `DateTimeInterface`, Unix timestamp или `null`.

Защита:

1. Все значения XML-текста и атрибутов экранируются.
2. Имя XML-тега проверяется по ограниченному набору символов; служебные теги YML нельзя переопределить.
3. Абсолютные URL разрешены только для того же origin, что и доверенный базовый URL.
4. Относительные ссылки формируются через `UrlGenerator`; `HTTP_HOST` и `SERVER_NAME` не используются.
5. Описание очищается от HTML и приводится к обычному тексту.
6. Нулевые значения полей не теряются из-за `empty()`.

Метод `render()` возвращает XML-строку и ничего не выводит. Отправка HTTP-заголовков и `echo` остаются ответственностью контроллера или совместимого фасада.

## BitrixCatalogExporter

`Project\Export\BitrixCatalogExporter` получает товары и разделы через `ElementRepository` и `SectionRepository`, подготавливает текст/URL/изображения и сохраняет каталог в `JsonFileCache`.

```php
$urls = new \Project\Support\UrlGenerator('https://shop.example.org');

$exporter = new \Project\Export\BitrixCatalogExporter(
    12,
    $urls,
    '/var/www/site/bitrix/catalog_export/cache',
    [
        'sectionIds' => [5],
        'productIds' => [],
        'additionalData' => [],
        'cacheTtl' => 86400,
    ]
);

$data = $exporter->loadData([], true);

$renderer = new \Project\Export\YmlCatalogRenderer($urls, [
    'shopName' => 'Магазин',
    'companyName' => 'ООО Пример',
]);

echo $renderer->render(
    $data['sections'],
    $data['products'],
    ['fields' => ['price' => 'PRICE']],
    $exporter->getGeneratedAt()
);
```

Конструктор принимает:

1. ID инфоблока;
2. объект `UrlGenerator` с доверенным URL;
3. абсолютный путь к каталогу кеша;
4. настройки;
5. необязательные `ElementRepository` и `SectionRepository`;
6. необязательный `JsonFileCache`;
7. необязательную функцию преобразования ID файла в путь к изображению.

Настройки:

| Параметр | Назначение |
|---|---|
| `sectionIds` | Корневые разделы для выгрузки вместе с потомками |
| `productIds` | Если список не пуст, ограничивает товары указанными ID |
| `additionalData` | Дополнительные данные экспорта, сохраняются в кеше |
| `cacheTtl` | Время жизни кеша в секундах, по умолчанию 86400 |
| `cacheKey` | Имя кеша; по умолчанию уникально для ID инфоблока |

Методы:

- `setNeedUpdate($flag)` — принудительно пометить каталог на обновление;
- `loadData(array $params = [], $return = false)` — загрузить данные или вернуть массив при `$return = true`;
- `loadAdditionalData(array $data = [])` — заменить дополнительные данные и пометить кеш устаревшим;
- `getAdditionalData()`, `getProducts()`, `getSections()`;
- `getGeneratedAt()` — время формирования текущего набора данных.

При обновлении вызывается защищённый хук `prepareAdditionalData()`. Старый адаптер `Lema\\Base\\XmlExport` дополнительно вызывает переопределённый наследником метод `loadAdditionalData()` только при обновлении кеша. Это сохраняет старую точку расширения, не стирая данные, переданные в конструктор.

В `$params` для `loadData()` передаются старые ключи запроса инфоблока: `order`, `filter`, `bIncCnt`, `arNavStartParams`, `arSelect`; дополнительно поддерживаются `limit` и `callback`.

Если передан `callback` или собственный `picturePathResolver`, результат не читается из кеша и не записывается в него: произвольная функция может менять данные, и безопасно определить её версию автоматически невозможно. Для обычной выгрузки подпись кеша учитывает ID инфоблока, базовый URL, выбранные разделы/товары, дополнительные данные и параметры запроса. По умолчанию имя кеша также включает ID инфоблока.

Разделы загружаются вместе с потомками по LEFT/RIGHT_MARGIN. В отличие от стандартного `SectionRepository::getList()`, для этой операции не навязывается `ACTIVE=Y`: дерево выгрузки сохраняет и неактивные разделы. Родитель дочернего раздела определяется по `IBLOCK_SECTION_ID`.

Для изображений используется `CFile::GetPath()`. В CLI-тестах или при нестандартном хранилище можно передать `picturePathResolver` в конструктор; при этом кеширование отключается, поскольку результат пользовательской функции нельзя надёжно идентифицировать. Модуль `iblock` подключается репозиториями непосредственно перед запросом.

## Миграция старых Lema-классов

Старые имена доступны через адаптеры:

- `local/php_interface/legacy/Lema/Base/XmlExport.php` — `Lema\Base\XmlExport`;
- `local/php_interface/legacy/Lema/Seo/YmlExport.php` — `Lema\Seo\YmlExport`.

Подключение:

```php
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/legacy/Lema/Base/XmlExport.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/legacy/Lema/Seo/YmlExport.php';

$export = new \Lema\Seo\YmlExport(12, [
    'serverUrl' => 'https://shop.example.org',
    'storageDir' => '/bitrix/catalog_export/export',
    'sectionIds' => [5],
    'shopName' => 'Магазин',
    'companyName' => 'ООО Пример',
]);

$export->loadData();
$export->showData([
    'sendHeader' => true,
    'fields' => ['price' => 'PRICE'],
]);
```

`storageDir` в старом формате остаётся путём относительно `DOCUMENT_ROOT`. Переходы `.` и `..` запрещены. `serverUrl` теперь обязателен и должен содержать доверенный адрес сайта; прежняя подстановка `HTTP_HOST` небезопасна.

`Lema\Seo\YmlExport::showData()` сохраняет старый побочный эффект `echo`. Заголовок отправляется как `application/xml; charset=UTF-8`, если `sendHeader=true` и заголовки ещё не отправлены.

Базовый `Lema\Base\XmlExport::showData()` раньше содержал заглушку `...`, а не полноценную XML-схему. Теперь он явно выбрасывает `LogicException`; формат должен задаваться наследником.

### Отличия от старой реализации

1. Вместо трёх несогласованных файлов `products.json`, `sections.json`, `additionalData.json` используется один атомарно обновляемый кеш каталога.
2. `additionalData` действительно сохраняется и восстанавливается.
3. Проверяются ошибки JSON и файловых операций.
4. Кеш блокируется и не отдаёт частично записанный JSON.
5. Параметры запроса и выбранные ID входят в подпись кеша; разные выгрузки не используют один и тот же набор данных.
6. Произвольный callback отключает кеширование.
7. `productIds`, которые в старой версии объявлялись, но не применялись, теперь ограничивают выборку.
8. `sectionIds` обрабатываются как корневые разделы с потомками.
9. URL строятся от заданного доверенного адреса; внешние домены в URL товаров отклоняются.
10. Вывод всегда формируется в UTF-8; не используется `SITE_CHARSET` из глобального окружения.

## Проверки

```bash
cd local
composer test:export
```

Автономный тест проверяет JSON-кеш, блокировку/атомарную запись, XML-экранирование, YML-поля, URL и подготовку данных через внедрённые репозитории.

Реальная выгрузка из установленного Битрикс, `CFile::GetPath()`, `CIBlockElement`, `CIBlockSection` и публикация YML по HTTP требуют отдельной интеграционной проверки на проекте.
