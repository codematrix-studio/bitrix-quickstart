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

### Выборка разделов без ограничения активности

Для экспортных задач добавлен `SectionRepository::getListIncludingInactive($iblockId, $params)`. Метод сохраняет порядок аргументов `CIBlockSection::GetList()`, но не добавляет фильтр `ACTIVE=Y`. Это позволяет выгружать всё дерево разделов, включая отключённые разделы. Обычный `getList()` продолжает использовать прежнее поведение с активными разделами по умолчанию.

### Загрузка модуля

Модуль `iblock` больше не подключается побочным эффектом при загрузке PHP-файла. Репозиторий подключает его непосредственно перед запросом и выбрасывает `RuntimeException`, если модуль недоступен. Это делает ошибку явной и позволяет подключать классы в CLI и тестах без установленного ядра.



### Совместимость старого фасада

В `local/php_interface/legacy/Lema/IBlock/Element.php` и `Section.php` сохранены прежние статические имена методов и константы `CLASS_NAME` / `OLD_CLASS_NAME`. Фасады делегируют запросы репозиториям. Динамические вызовы D7-методов через `__callStatic()` по-прежнему обеспечивают подключение модуля `iblock`, но теперь делают это непосредственно перед вызовом, а не побочным эффектом при подключении PHP-файла.

`Element::getListPagination()` отдельно сохраняет старый порядок действий: `CIBlockElement::GetList()`, `NavStart(nPageSize)`, затем `NavNext(true)`. Поэтому возвращаемые строки сохраняют форматирование legacy API. Для этого метода обязательно передавать `arNavStartParams['nPageSize']`; при отсутствии размера страницы выбрасывается понятное исключение вместо PHP warning.

При переносе существующего проекта сначала подключите Composer autoload, затем нужные файлы адаптеров. Не подключайте старый модуль `lema.lib` одновременно с адаптерами — это приведёт к конфликту имён классов.

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


## Миграция старого `Ns\Bitrix\Helper\IBlock\Checker`

В исходном проекте `ASDAFF/bitrix.quickstart` старый `Checker` предоставлял два метода:

- `isUnique()` — возвращал `true`, если элемент с заданным фильтром **не найден**;
- `existedID()` — возвращал ID найденного элемента либо `false`.

Фильтр передавался через магический вызов `withParams()`, унаследованный от `HelperCore`. В новом каркасе фильтр передаётся непосредственно в метод проверки — состояние не хранится внутри объекта.

Пример переноса на старый API Битрикс:

```php
use Project\Bitrix\IBlock\LegacyElementChecker;

$checker = new LegacyElementChecker();

$filter = [
    'IBLOCK_ID' => 12,
    '=NAME' => 'Пример',
];

// Аналог старого isUnique().
$isUnique = !$checker->exists($filter);

// Аналог existedID(): новый API возвращает null вместо false.
$elementId = $checker->findId($filter);
$legacyElementId = $elementId ?? false;
```

Для D7 используйте `ElementChecker`, но учитывайте, что формат фильтра `ElementTable` отличается от фильтра `CIBlockElement::GetList()`. В legacy-варианте первый аргумент `GetList()` сохранён как `false`, как в исходном `IBlock\Checker`.

Пустой фильтр намеренно запрещён: это защищает от случайного запроса ко всем элементам инфоблоков. Модуль `iblock` должен быть подключён до вызова методов.

Автономные проверки обоих классов запускаются командой `composer test:iblock` из каталога `local`. Они проверяют передаваемые параметры, ограничение выборки одной записью, возврат ID/`null` и отказ от пустого фильтра. Проверки не заменяют интеграционный запуск на реальном ядре Битрикс.


### Почему `HelperCore` не переносится

Старый `HelperCore` не содержит предметной логики: он реализует только общий контейнер состояния через магические вызовы `with...()` и `get...()`. Имена методов разбираются поиском подстрок, значения не проверяются, а чтение незаданного ключа может вызвать предупреждение PHP. Поскольку состояние хранится в объекте, повторное использование экземпляра также требует осторожности.

Для проверки элементов этот механизм заменён явным аргументом `$filter`. Остальные классы, наследовавшие `HelperCore`, следует переносить отдельно: их параметры и зависимости лучше выражать обычными аргументами методов, конструкторами или небольшими типизированными объектами, а не сохранять универсальный магический базовый класс.

## Подготовка результата компонента для шаблона: `TemplateHelper`

`Project\Bitrix\IBlock\TemplateHelper` переносит полезную часть старого
`Lema\Template\TemplateHelper`: регистрирует штатные действия редактирования
и удаления элементов/разделов и возвращает записи как объекты представления.

### Список элементов

```php
use Project\Bitrix\IBlock\TemplateHelper;

$view = new TemplateHelper($this);

foreach ($view->items() as $item) {
    ?>
    <article<?= $item->editId(); ?>>
        <h2><?= htmlspecialchars((string) $item->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
    </article>
    <?php
}
```

Для корректной регистрации действий в параметрах компонента должен быть указан
`IBLOCK_ID`. Если параметра нет, класс попробует определить ID по полю
`IBLOCK_ID` первой подходящей записи. Каждая запись списка должна содержать
`ID`; ссылки `EDIT_LINK` и `DELETE_LINK` передаются штатным методам шаблона
без самостоятельного формирования.

### Список разделов

Если в результате есть ключ `SECTIONS` и нет `ITEMS`, используются
`SectionView`, типы действий `SECTION_EDIT` / `SECTION_DELETE` и штатное
сообщение `CT_BCSL_ELEMENT_DELETE_CONFIRM`.

Если присутствуют оба ключа, сохраняется поведение старого класса: приоритет
имеет `ITEMS`.

### Методы

- `items(bool $objectData = true)` — коллекция элементов или разделов;
- `sections(bool $objectData = true)` — алиас `items()` для шаблонов разделов;
- `item()` — одиночный результат как `ElementView`;
- `itemCount()` — число записей в коллекции;
- `get($name)`, `getId()`, `getName()` — доступ к полям результата.

При `objectData=false` методы коллекции возвращают исходные массивы, а не
объекты. Одиночный результат не регистрирует действия редактирования: в нём
нет гарантированного массива ссылок `EDIT_LINK` / `DELETE_LINK`, поэтому
регистрацию следует выполнять только для коллекций, как это делал исходный код.

### Отличия и ограничения миграции

- Класс требует объект шаблона с методами `getComponent()`,
  `AddEditAction()`, `AddDeleteAction()` и `GetEditAreaId()`.
- Для коллекции требуется доступный класс `CIBlock` и определимый `IBLOCK_ID`.
  Модуль `iblock` должен быть подключён до создания помощника.
- Некорректная структура результата (не массив коллекции или запись без `ID`)
  приводит к явному исключению вместо предупреждений PHP.
- `item()` для результата-списка теперь выбрасывает `LogicException`, а не
  возвращает случайный массив.
- Объекты `ElementView` и `SectionView` остаются отдельными read-only
  представлениями; регистрация действий вынесена в помощник.
- Класс не подключает модуль `iblock` автоматически и не меняет данные
  `arResult` компонента.

Автономная проверка без ядра Битрикс: `tests/template_helper_smoke.php`.
Запуск из каталога `local`: `composer test:template`. Реальную работу
контекстного меню в административной панели необходимо дополнительно проверить
на установленном ядре Битрикс.


## Пользовательские типы свойств инфоблоков

Пользовательские типы свойств размещаются в каталоге `Property`. Первая реализация — строковое свойство «Да/Нет» с флажком: `Project\Bitrix\IBlock\Property\YesNoProperty`. Подробности: [Property/README.md](Property/README.md).
