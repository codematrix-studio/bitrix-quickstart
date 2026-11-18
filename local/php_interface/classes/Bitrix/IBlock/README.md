# Работа с элементами инфоблока

В каркасе есть отдельные классы для проверки элементов и генерации их символьных кодов. Выбирайте реализацию проверки в зависимости от API, который используется в проекте.

## D7 ORM: `Project\Bitrix\IBlock\ElementChecker`

Перед использованием должен быть подключён модуль `iblock`.

### Проверка существования

```php
use Project\Bitrix\IBlock\ElementChecker;

$checker = new ElementChecker();

$exists = $checker->exists([
    '=IBLOCK_ID' => 12,
    '=CODE' => 'example',
]);
```

### Получение ID

```php
$elementId = $checker->findId([
    '=IBLOCK_ID' => 12,
    '=CODE' => 'example',
]);

if ($elementId !== null) {
    // Элемент найден.
}
```

Методы принимают фильтр D7 ORM для `Bitrix\Iblock\ElementTable`.

## Legacy API: `Project\Bitrix\IBlock\LegacyElementChecker`

Используется в проектах, где работа с инфоблоками построена на `CIBlockElement::GetList()`. Модуль `iblock` также должен быть подключён.

### Проверка существования

```php
use Project\Bitrix\IBlock\LegacyElementChecker;

$checker = new LegacyElementChecker();

$exists = $checker->exists([
    'IBLOCK_ID' => 12,
    '=CODE' => 'example',
]);
```

### Получение ID

```php
$elementId = $checker->findId([
    'IBLOCK_ID' => 12,
    '=CODE' => 'example',
]);

if ($elementId !== null) {
    // Элемент найден.
}
```

Методы принимают фильтр в формате старого API `CIBlockElement::GetList()`.

## Генерация символьного кода: `Project\Bitrix\IBlock\ElementCodeGenerator`

Класс использует штатный `CUtil::translit()` из главного модуля Битрикс. Перед использованием должен быть доступен класс `CUtil`.

```php
use Project\Bitrix\IBlock\ElementCodeGenerator;

$codeGenerator = new ElementCodeGenerator();

$code = $codeGenerator->generate('Название элемента инфоблока');
// Например: nazvanie-elementa-infobloka
```

Сохранены правила старого помощника: язык транслитерации `ru`, пробелы и остальные неподходящие символы заменяются дефисами.

Класс не проверяет уникальность полученного кода и не сохраняет элемент. Уникальность необходимо учитывать отдельно при создании элементов.

## Общие особенности проверки

Оба класса проверки предоставляют одинаковый интерфейс:

- `exists(array $filter): bool` — возвращает, найден ли элемент;
- `findId(array $filter): ?int` — возвращает ID найденного элемента или `null`.

Пустой фильтр запрещён, чтобы случайно не выполнять поиск по всей таблице. Оба класса выбирают только поле `ID` и ограничивают выборку одной записью.

**Важно:** синтаксис фильтров D7 ORM и Legacy API не полностью совпадает. Передавайте фильтр в формате соответствующего API. Классы не преобразуют фильтры друг в друга и не загружают модуль `iblock` самостоятельно.

## Представление результата инфоблока

`Project\Bitrix\IBlock\ElementView` — объект-обёртка над массивом элемента, полученным из компонента или API Битрикс. Он сохраняет привычные методы старого `Lema\Template\Item`: `get()`, `prop()`, `propValue()`, `propText()`, `getId()`, `getName()`, `previewText()`, `detailText()`, `detailUrl()`, `previewPicture()` и другие.

`Project\Bitrix\IBlock\SectionView` расширяет его и учитывает формат полей изображения раздела (`PICTURE` и `DETAIL_PICTURE`).

```php
$item = new \Project\Bitrix\IBlock\ElementView($arItem, $editAreaId);
$name = $item->getName();
$price = $item->propValue('PRICE');
$preview = $item->previewPicture();
```

Для миграции доступны необязательные адаптеры `local/php_interface/legacy/Lema/Template/Item.php` и `local/php_interface/legacy/Lema/Template/Section.php`. После Composer autoload и подключения соответствующего файла старые имена `Lema\Template\Item` и `Lema\Template\Section` продолжают работать. Адаптеры не подключаются автоматически, чтобы не конфликтовать с установленным модулем `lema.lib`.

## Репозитории элементов и разделов

Старые `Lema\\IBlock\\Element` и `Lema\\IBlock\\Section` объединяли D7 ORM и legacy API в статических фасадах. Новая реализация разделяет запросы по репозиториям:

- `Project\\Bitrix\\IBlock\\ElementRepository`;
- `Project\\Bitrix\\IBlock\\SectionRepository`.

### Пример

```php
$elements = new \\Project\\Bitrix\\IBlock\\ElementRepository();
$activeItems = $elements->getListD7($iblockId, [
    'select' => ['ID', 'NAME', 'CODE'],
]);

$sections = new \\Project\\Bitrix\\IBlock\\SectionRepository();
$activeSections = $sections->getList($iblockId, [
    'order' => ['SORT' => 'ASC'],
    'arSelect' => ['ID', 'NAME', 'CODE'],
]);
```

### Методы ElementRepository

| Метод | Назначение |
|---|---|
| `getById($elementId, $params = [])` | Получить элемент через D7 `getByPrimary()` |
| `getListD7($iblockId, $params = [])` | Получить активные элементы через D7 |
| `getList($iblockId, $params = [])` | Получить элементы через `CIBlockElement::GetList()` |
| `getCount($iblockId, $params = [])` | Получить количество через D7 |
| `getListPagination($iblockId, $params = [])` | Получить текущую выборку с legacy-параметрами навигации |
| `getAllD7($iblockId, $params = [])` | Получить все подходящие элементы через D7 без принудительного ACTIVE |
| `getAll($iblockId, $params = [], $pagination = false)` | Старый общий метод для legacy API |

### Методы SectionRepository

| Метод | Назначение |
|---|---|
| `getListD7($iblockId, $params = [])` | Активные разделы через D7 |
| `getList($iblockId, $params = [])` | Разделы через `CIBlockSection::GetList()` |
| `getAllD7($iblockId, $params = [])` | Все подходящие разделы через D7 без принудительного ACTIVE |
| `getAll($iblockId, $params = [])` | Общий метод для legacy API |

### Параметры старого API

Публичные методы сохраняют старые ключи массива:

- `order` — сортировка;
- `filter` — фильтр;
- `bIncCnt` — группировка/подсчёт для legacy API;
- `arNavStartParams` — параметры навигации;
- `arSelect` — выбираемые поля.

Для элементов порядок аргументов `CIBlockElement::GetList` сохранён: сортировка, фильтр, группировка, навигация, выборка. Для разделов сохранён порядок `CIBlockSection::GetList`: сортировка, фильтр, подсчёт, выборка, навигация.

Результат по-прежнему представляет собой массив строк, индексированный по ID. D7-методы `getListD7()` принудительно ограничивают выборку указанным инфоблоком и активными записями. `getAllD7()` ограничивает инфоблок, но не навязывает ACTIVE=Y, как и старый метод.

### Загрузка модуля

Модуль `iblock` больше не подключается побочным эффектом при загрузке PHP-файла. Репозиторий подключает его непосредственно перед запросом и выбрасывает `RuntimeException`, если модуль недоступен. Это делает ошибку явной и позволяет подключать классы в CLI и тестах без установленного ядра.

### Тестирование без ядра

Репозитории принимают необязательные callback-зависимости для загрузчика модуля и запросов. Благодаря этому можно проверить состав и порядок параметров без подмены классов ядра.

Запуск тестов:

```bash
cd local
composer test:iblock
```

Интеграционный тест с реальными `ElementTable`, `SectionTable`, `CIBlockElement` и `CIBlockSection` всё равно необходим на установленном ядре Битрикс.


## Дополнительные утилиты инфоблоков

### PropertyAccessor

`Project\\Bitrix\\IBlock\\PropertyAccessor` содержит статические методы для стандартного массива свойств элемента или раздела:

- `isEmpty($code, $data, $propertiesKey = 'PROPERTIES')`;
- `isFilled(...)`;
- `get(...)` — получить массив свойства;
- `getValue(...)` — получить `VALUE`;
- `getEscapedValue(...)` — получить скалярное значение, экранированное для HTML.

Класс не обращается к базе данных и не загружает модуль `iblock`. Он работает только с уже полученным массивом.

### ImageWatermarker

`Project\\Bitrix\\IBlock\\ImageWatermarker` инкапсулирует вызов `CFile::ResizeImageGet()` и параметры водяного знака. Он проверяет наличие обязательных полей и класса `CFile`. Если тип масштабирования не передан, требуется штатная константа `BX_RESIZE_IMAGE_PROPORTIONAL`.

Метод `resize()` возвращает штатный результат Битрикс либо `false` при неполных данных. `getSource()` возвращает путь к обработанному изображению или `false`.
