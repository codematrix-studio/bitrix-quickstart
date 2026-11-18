# Классы проекта

Размещайте здесь собственные PHP-классы проекта.

Composer PSR-4 в `local/composer.json` связывает namespace `Project\\` с этим каталогом.

Например:

- файл: `local/php_interface/classes/Services/CatalogService.php`
- namespace: `Project\Services`
- класс: `CatalogService`

После добавления или изменения классов обновите автозагрузку:

```bash
composer dump-autoload --working-dir=local
```

## Добавленные базовые классы

### `Project\Support\EmailValidator`

Проверяет синтаксическую корректность email через `FILTER_VALIDATE_EMAIL`.
Не выполняет DNS-проверку и не подтверждает существование почтового ящика.

```php
$validator = new \Project\Support\EmailValidator();

if ($validator->isValid($email)) {
    // Адрес имеет допустимый формат.
}
```

### `Project\Support\HtmlEscaper`

Кодирует специальные HTML-символы для вывода в HTML-текст или атрибут в кавычках.
Используйте его непосредственно при выводе данных, а не как универсальную очистку входных данных.

```php
$escaper = new \Project\Support\HtmlEscaper();

echo $escaper->escape($value);
```

Оба класса не зависят от API Битрикс и не требуют сторонних Composer-пакетов.

## Bitrix D7

### `Project\Bitrix\IBlock\ElementChecker`

Проверяет наличие элемента инфоблока по фильтру `ElementTable`:

- `exists(array $filter): bool` — возвращает, найден ли элемент;
- `findId(array $filter): ?int` — возвращает ID найденного элемента или `null`.

Требует загруженного модуля `iblock`. Пустой фильтр не допускается.
Подробные примеры находятся в [Bitrix/IBlock/README.md](Bitrix/IBlock/README.md).
