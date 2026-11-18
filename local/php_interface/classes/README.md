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

## Базовые классы Support

### `Project\Support\EmailValidator`

Проверяет синтаксическую корректность email через `FILTER_VALIDATE_EMAIL`. Удаляет пробелы по краям перед проверкой, но не изменяет переданную переменную и не возвращает нормализованный адрес.

Не выполняет DNS-проверку и не подтверждает существование почтового ящика.

```php
$validator = new \Project\Support\EmailValidator();
$email = trim($email);

if ($validator->isValid($email)) {
    // Адрес имеет допустимый формат.
}
```

### `Project\Support\HtmlEscaper`

Кодирует специальные HTML-символы для вывода в HTML-текст или атрибут в кавычках.
Это контекстное кодирование при выводе, а не универсальная очистка входных данных.

```php
$escaper = new \Project\Support\HtmlEscaper();

echo $escaper->escape($name);
```

Оба класса не зависят от API Битрикс и не требуют сторонних Composer-пакетов.

## Замена старого `Ns\Bitrix\Helper\IBlock\Validator`

Старый класс не переносится как отдельный `IBlock\Validator`: он не выполнял операций с инфоблоками и содержал две несвязанные задачи:

- `email()` — проверка формата email с возвратом адреса или `false`;
- `xss()` — `trim()` и `htmlspecialchars()` для входного значения.

Эти задачи разделены между `EmailValidator` и `HtmlEscaper`. Интерфейс намеренно не копируется один в один: валидатор возвращает `bool`, а HTML-кодирование выполняется в месте вывода, с учётом контекста. Не следует сохранять HTML-сущности в базе вместо исходного текста только ради защиты от XSS.

Пример обработки данных:

```php
$email = trim($email);
$emailValidator = new \Project\Support\EmailValidator();

if (!$emailValidator->isValid($email)) {
    // Обработать некорректный адрес.
}

$name = trim($name);
// Сохранять исходный текст, а HTML-кодировать при выводе:
echo (new \Project\Support\HtmlEscaper())->escape($name);
```

## Bitrix: проверка элементов инфоблока

Для проектов на D7 ORM используйте `Project\Bitrix\IBlock\ElementChecker`.

Для проектов на старом API используйте `Project\Bitrix\IBlock\LegacyElementChecker`.

Оба класса имеют методы `exists()` и `findId()`, но принимают фильтры в формате своего API. Подробные примеры и различия описаны в [Bitrix/IBlock/README.md](Bitrix/IBlock/README.md).
