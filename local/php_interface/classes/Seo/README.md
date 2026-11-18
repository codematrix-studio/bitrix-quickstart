# SEO и meta-разметка

## MetaMarkup

`Project\\Seo\\MetaMarkup` — базовый генератор HTML meta-тегов. Он хранит значения без предварительного HTML-кодирования и экранирует имя и значение атрибута непосредственно при формировании разметки.

Пример:

```php
$meta = new \\Project\\Seo\\MetaMarkup([
    'description' => 'Описание страницы',
]);

$meta->setItem('title', 'Название страницы');
echo $meta->getMeta(true);
```

Основные методы:
- `setItem($type, $content)` — установить один meta-тег;
- `setItems(array $data)` — заменить набор тегов;
- `getItem($type)` — получить исходное значение;
- `getItemMeta($type)` — получить один готовый тег;
- `getMeta($return = false)` — вернуть или вывести все теги;
- `setViewContent()`, `show()` — интеграция с буфером представления Битрикс;
- `setImage()`, `getImage()` — работа с изображением в буфере представления.

Для совместимости сохранено правило старой библиотеки: если одновременно заданы пустой `title` и непустой `title2`, значение `title2` переносится в `title`, а `title2` удаляется.

В отличие от старого кода, HTML-экранирование выполняется при формировании атрибутов. Это предотвращает двойное экранирование при последовательных вызовах `setItem()` и `setItems()`.

## OpenGraph

Старый класс `Lema\\Seo\\OpenGraph` сохранён адаптером. Он использует префикс `og:` и прежний fluent API:

```php
$og = \\Lema\\Seo\\OpenGraph::get();
$og->setItem('title', 'Название страницы')
   ->setItem('description', 'Описание');

echo $og->getMeta(true);
```

Адаптеры:
- `local/php_interface/legacy/Lema/Base/Markup.php`;
- `local/php_interface/legacy/Lema/Seo/OpenGraph.php`.

Подключать после Composer autoload. Для работы `setViewContent()`, `show()` и методов изображения необходим глобальный объект `$APPLICATION` Битрикс.

## Проверки

```bash
cd local
composer test:markup
```

Тест проверяет экранирование, префикс OpenGraph, правило title/title2 и взаимодействие с буфером представления через тестовый объект приложения.
