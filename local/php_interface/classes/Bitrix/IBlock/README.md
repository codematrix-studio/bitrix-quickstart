# Проверка элементов инфоблока

## `Project\Bitrix\IBlock\ElementChecker`

Класс проверяет наличие элемента инфоблока по фильтру D7 ORM.

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

Методы принимают стандартный фильтр D7 ORM для `Bitrix\Iblock\ElementTable`.
Пустой фильтр запрещён, чтобы случайно не выполнять поиск по всей таблице.
Выборка ограничена одной записью.

Класс не задаёт ID инфоблока или другие проектные параметры самостоятельно.
