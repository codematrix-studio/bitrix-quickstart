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

## Совместимость со старым IBlock Validator

Старый класс `Ns\\Bitrix\\Helper\\IBlock\\Validator` использовал два метода:

- `email($email = '', $xss = true)` — возвращал очищенный адрес или `false`;
- `xss($value = '')` — обрезал внешние пробелы и экранировал HTML.

Для миграции добавлен необязательный адаптер `legacy/Ns/Bitrix/Helper/IBlock/Validator.php`. Он использует `Project\\Support\\HtmlEscaper` и сохраняет особенности старого контракта: при `$xss = false` значение не обрезается, а `email()` возвращает строку, а не boolean.

Пример подключения после Composer autoload:

```php
require_once __DIR__ . '/legacy/Ns/Bitrix/Helper/IBlock/Validator.php';

$validator = new \\Ns\\Bitrix\\Helper\\IBlock\\Validator();
$email = $validator->email($rawEmail);
$name = $validator->xss($rawName);
```

Это переходный API. В новом коде используйте `Project\\Support\\EmailValidator` для проверки адреса и `Project\\Support\\HtmlEscaper` непосредственно перед HTML-выводом. Не следует хранить HTML-экранированные значения в базе данных.

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


## Базовые классы экземпляров

### PerClassInstance

`Project\\Support\\PerClassInstance` хранит отдельный экземпляр для каждого класса-наследника. Это исключает ситуацию, когда два разных наследника получают один и тот же объект из общего унаследованного статического свойства.

- `get(): static` — получить или создать экземпляр текущего класса;
- `resetInstance(): void` — сбросить экземпляр текущего класса.

### PerClassGlobalProxy

`Project\\Support\\PerClassGlobalProxy` получает глобальный объект по имени, которое возвращает метод-наследник `globalName()`. Кеш объектов изолирован по классу. Магический вызов `__call()` передаёт аргументы метода отдельно, а не одним массивом.

### Перенос из lema.lib

- `Lema\\Base\\StaticInstance` использует `PerClassInstance`;
- `Lema\\Base\\GlobalVars` использует `PerClassGlobalProxy`;
- `Lema\\Base\\BitrixInstances` использует `Project\\Bitrix\\Context\\PerClassBitrixInstance`.

Адаптеры находятся в `local/php_interface/legacy/Lema/Base/`. Тесты запускаются командой `composer test:base`.


## JsonFileCache

`Project\Support\JsonFileCache` — файловый кеш массивов в JSON. Подробности описаны в [Export/README.md](../Export/README.md), поскольку класс используется при подготовке XML/YML-каталогов.

- `get($key)` — прочитать массив или получить `null` при отсутствии/повреждении кеша;
- `put($key, array $data)` — атомарно записать JSON;
- `isFresh($key, $ttl)` — проверить возраст кеша;
- `delete($key)` — удалить кеш-файл.

Запись защищена блокировкой `flock()` и выполняется через временный файл с последующим `rename()`. Ключ не может содержать путь или `..`.


## PagePathMatcher

`Project\\Support\\PagePathMatcher` проверяет принадлежность URL-каталога другому каталогу без обращения к Bitrix:

```php
use Project\\Support\\PagePathMatcher;

if (PagePathMatcher::inDirectory('/catalog/item/', '/catalog/')) {
    // Текущий адрес — каталог или его вложенная страница.
}

if (PagePathMatcher::inRootDirectory('/catalog/item/', ['catalog', 'news'])) {
    // Первый сегмент URL — один из корневых разделов.
}
```

- `inDirectory($currentPath, $directories)` — совпадение с каталогом или его потомками;
- `inRootDirectory($currentPath, $sections)` — совпадение только с первым сегментом URL;
- принимает строку или массив строк;
- нормализует начальные/конечные и повторяющиеся слеши;
- учитывает границы сегментов: `/catalog/` не совпадает с `/catalog-old/`;
- для корневых разделов сравнение регистронезависимое, для обычных каталогов — регистрозависимое.

Класс не читает `$_SERVER`, не зависит от глобального состояния и подходит для автономного тестирования.


## Регистрация ресурсов Bitrix

Для добавления JS/CSS и инициализации расширений используйте `Project\\Bitrix\\Asset\\AssetManager`. Класс находится в пространстве имён `Project\\Bitrix\\Asset`, поэтому его описание вынесено в [Bitrix/Asset/README.md](../Bitrix/Asset/README.md). Он зависит от ядра Bitrix только при стандартном создании; для автономных тестов поддерживает внедрение объекта Asset и инициализатора CJSCore.
