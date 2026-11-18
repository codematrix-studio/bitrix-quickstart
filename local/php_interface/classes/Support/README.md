# Вспомогательные классы

В этом каталоге находятся небольшие универсальные классы, не зависящие от ядра Битрикс.

## RussianPluralizer

Подбирает одну из трёх форм существительного:

```php
use Project\Support\RussianPluralizer;

echo RussianPluralizer::withNumber(5, ['товар', 'товара', 'товаров']);
// 5 товаров
```

Методы:
- `word($number, array $forms)` — вернуть форму слова;
- `withNumber($number, array $forms, $separator = ' ')` — вернуть число и форму слова.

Для неполного набора форм или нечислового значения возвращает `false`. Отрицательные числа обрабатываются по модулю числа.

## JsonEncoder

Обёртка над `json_encode()`, сохраняющая стандартный результат PHP: строку JSON или `false`.

```php
$json = JsonEncoder::encode($data, JSON_UNESCAPED_UNICODE);
```

Параметры: данные, опции кодирования и максимальная глубина.

## CharsetConverter

Преобразует строки между UTF-8 и Windows-1251:

- `toWindows1251(string $value, bool $ignoreInvalid = false)`;
- `fromWindows1251(string $value, bool $ignoreInvalid = false)`.

При невозможности преобразования возвращает `false`, как и `iconv()`. Режим `ignoreInvalid` добавляет флаг `//IGNORE`.

## UrlGenerator

Формирует абсолютные URL от явно заданного базового адреса:

```php
$urls = new UrlGenerator('https://example.org');
$url = $urls->absolute('/catalog/item/');
// https://example.org/catalog/item/
```

В отличие от старого `Helper::getFullUrl()`, класс не доверяет `HTTP_HOST` и `SERVER_NAME` входящего запроса. Принимается только базовый URL с протоколом HTTP/HTTPS без логина и пароля. Путь не может быть внешним URL или начинаться с `//`.

## HtmlEscaper

`escape()` экранирует строку для HTML. Дополнительно принимает флаги и кодировку:

```php
$html = (new HtmlEscaper())->escape($value, ENT_COMPAT, 'UTF-8');
```

Значения по умолчанию сохранены: `ENT_QUOTES | ENT_SUBSTITUTE`, UTF-8.

## Миграция из lema.lib

Для старого имени `Lema\\Common\\Helper` создан адаптер `local/php_interface/legacy/Lema/Common/Helper.php`.

Сохранены методы:
- `pluralize()`, `pluralizeN()`;
- `enc()`, `getJson()`, `utf8ToCP1251()`;
- `getFullUrl()`;
- `getImageWatermark()`, `getImageWatermarkSrc()`;
- `propEmpty()`, `propFilled()`, `prop()`, `propValue()`, `escPropValue()`.

Для `getFullUrl()` теперь обязательно один раз задать доверенный адрес сайта:

```php
\\Lema\\Common\\Helper::setBaseUrl('https://example.org');
$url = \\Lema\\Common\\Helper::getFullUrl('/catalog/');
```

Это осознанное изменение безопасности: нельзя строить платёжные и другие абсолютные ссылки из непроверенного заголовка Host.

Подробности по работе с инфоблоками и изображениями находятся в [Bitrix/IBlock/README.md](../Bitrix/IBlock/README.md).

## Проверки

```bash
cd local
composer test:helper
```

Общий набор:

```bash
composer test
```
