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

### `Project\Support\TextPreview`

Преобразует HTML или обычный текст в компактный текстовый анонс. По умолчанию ограничивает результат 300 символами, старается не обрезать последнее слово и добавляет многоточие только при фактическом сокращении.

Длина считается в Unicode-графемах, поэтому кириллица и составные символы не разрезаются посреди байтов или символа.

```php
$preview = new \Project\Support\TextPreview();

echo $preview->create($element['PREVIEW_TEXT'], 200);
```

Класс не зависит от API Битрикс и не требует расширения mbstring.

## Замена старого `Ns\Bitrix\Helper\IBlock\Validator`

Старый класс не переносится как отдельный `IBlock\Validator`: он не выполнял операций с инфоблоками и содержал две несвязанные задачи:

- `email()` — проверка формата email с возвратом адреса или `false`;
- `xss()` — `trim()` и `htmlspecialchars()` для входного значения.

Эти задачи разделены между `EmailValidator` и `HtmlEscaper`. Интерфейс намеренно не копируется один в один: валидатор возвращает `bool`, а HTML-кодирование выполняется в месте вывода, с учётом контекста. Не следует сохранять HTML-сущности в базе вместо исходного текста только ради защиты от XSS.

## Bitrix: работа с элементами инфоблока

Для проверки элементов используйте `Project\Bitrix\IBlock\ElementChecker` (D7 ORM) или `Project\Bitrix\IBlock\LegacyElementChecker` (старый API).

Для генерации символьного кода используйте `Project\Bitrix\IBlock\ElementCodeGenerator`. Для сокращения текста подходит независимый от Битрикс `Project\Support\TextPreview`.

Подробности и примеры приведены в [Bitrix/IBlock/README.md](Bitrix/IBlock/README.md).
