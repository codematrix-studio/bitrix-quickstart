# Проверка элементов инфоблока

В каркасе есть два отдельных класса для проверки элементов инфоблока. Выбирайте класс в зависимости от API, который используется в проекте.

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

## Общие особенности

Оба класса предоставляют одинаковый интерфейс:

- `exists(array $filter): bool` — возвращает, найден ли элемент;
- `findId(array $filter): ?int` — возвращает ID найденного элемента или `null`.

Пустой фильтр запрещён, чтобы случайно не выполнять поиск по всей таблице. Оба класса выбирают только поле `ID` и ограничивают выборку одной записью.

**Важно:** синтаксис фильтров D7 ORM и Legacy API не полностью совпадает. Передавайте фильтр в формате соответствующего API. Классы не преобразуют фильтры друг в друга и не загружают модуль `iblock` самостоятельно.
