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
