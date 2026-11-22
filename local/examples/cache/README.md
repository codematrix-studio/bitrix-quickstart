# Кеширование в 1С-Битрикс

Практические примеры штатного кеширования через D7 API.

## Состав

- [simple-data.php](simple-data.php) — кеширование результата произвольной функции через `Bitrix\Main\Data\Cache`.
- [tagged-data.php](tagged-data.php) — тот же сценарий с тегами для сброса кеша при изменении связанных данных.

## Подключение

Примеры содержат функции в пространстве имён `Project\Examples\Cache`, поэтому их можно подключить явно:

```php
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/examples/cache/simple-data.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/examples/cache/tagged-data.php';
```

## Обычный кеш

Передайте функцию, которая получает данные, уникальный ключ, каталог и время жизни:

```php
$items = \Project\Examples\Cache\getCachedData(
    static fn (): array => loadItemsFromDatabase(),
    'catalog-items-site-'.SITE_ID,
    '/my-project/catalog/items',
    3600
);
```

Ключ должен учитывать **все параметры, влияющие на результат**: сайт, раздел, фильтр, сортировку, язык и другие значимые условия. Не включайте в общий кеш персональные данные или результат, зависящий от текущего пользователя, если ключ не изолирует такой контекст.

## Тегированный кеш

Тегированный пример дополнительно принимает список тегов:

```php
$items = \Project\Examples\Cache\getTaggedCachedData(
    static fn (): array => loadItemsFromDatabase(),
    'catalog-items-site-'.SITE_ID,
    '/my-project/catalog/items',
    ['iblock_id_7', 'my_project_catalog_items'],
    3600
);
```

Каталог в `initCache()` и `startTagCache()` должен совпадать. При изменении данных сбрасывайте соответствующий тег:

```php
\Bitrix\Main\Application::getInstance()
    ->getTaggedCache()
    ->clearByTag('my_project_catalog_items');
```

Для инфоблоков Битрикс использует теги вида `iblock_id_<ID>`; штатные операции с инфоблоками могут сбрасывать их автоматически. Собственные теги нужно сбрасывать в коде, который изменяет соответствующие данные.

## Важные замечания

- `abortDataCache()` вызывается, если загрузка данных завершилась исключением: не сохраняйте неполный результат.
- Не очищайте кеш по произвольному `$_REQUEST` и не добавляйте публичный параметр принудительной очистки.
- Не используйте один ключ для разных наборов параметров.
- TTL — срок жизни в секундах; тег позволяет инвалидировать кеш раньше истечения TTL.
- Это примеры работы с данными, а не кеширование HTML-вывода компонента. Для компонента используйте его штатный механизм `StartResultCache()` / `AbortResultCache()` и регистрацию тегов внутри жизненного цикла компонента.
- Примеры требуют работающего ядра 1С-Битрикс и не проверялись на реальном стенде в рамках переноса.

## Источники API

- [D7 Cache](https://dev.1c-bitrix.ru/api_d7/bitrix/main/data/cache/index.php)
- [Урок по тегированному кешу и Cache Dependencies](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=33906)
