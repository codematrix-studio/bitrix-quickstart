# Работа с Highload-блоками

Для работы с HL-блоками используется слой `Project\Bitrix\HLBlock` поверх D7 ORM.

- `HlBlockManager` — публичная точка входа и кеш обёрток HL-блоков.
- `HlBlockRepository` — получение метаданных, подключение модуля, компиляция и кеширование ORM-классов.
- `HlBlockEntity` — чтение и изменение записей конкретного HL-блока.

## Получение HL-блока

Используйте числовой ID HL-блока. Поле `NAME` является названием и не применяется как технический идентификатор.

```php
use Project\Bitrix\HLBlock\HlBlockManager;

$manager = new HlBlockManager();
$records = $manager->getById(7);
```

Модуль `highloadblock` подключается при первом обращении к блоку. Метаданные и ORM-класс кешируются в пределах экземпляра менеджера.

## Чтение записей

```php
$items = $records->getList([
    'select' => ['ID', 'UF_XML_ID', 'UF_NAME'],
    'filter' => ['=UF_ACTIVE' => 1],
    'order' => ['ID' => 'DESC'],
    'limit' => 20,
]);

$item = $records->getById(15);
```

`getList()` возвращает массив записей, а `getById()` — массив записи или `null`.

### Поиск по UF_XML_ID

```php
$item = $records->findByXmlId('external-123');
```

Это дополнительный метод. Используйте его только для HL-блоков, в которых действительно создано поле `UF_XML_ID`. Для остальных полей и сложных условий остаётся доступен универсальный `getList()`.

## Добавление, обновление и удаление

```php
$recordId = $records->add([
    'UF_XML_ID' => 'external-123',
    'UF_NAME' => 'Новая запись',
]);

$records->update($recordId, ['UF_NAME' => 'Изменённая запись']);
$records->delete($recordId);
```

Публичный контракт сохранён:

| Метод | Результат |
|---|---|
| `HlBlockManager::getById(int $id)` | `HlBlockEntity` |
| `HlBlockEntity::getList(array $parameters = [])` | Массив записей |
| `HlBlockEntity::getById(int $id, array $select = ['*'])` | Запись или `null` |
| `HlBlockEntity::add(array $fields)` | ID новой записи |
| `HlBlockEntity::update(int $id, array $fields)` | `true` при успехе |
| `HlBlockEntity::delete(int $id)` | `true` при успехе |
| `HlBlockEntity::findByXmlId(string $xmlId, array $select = ['*'])` | Запись или `null` |

Ошибки D7 ORM преобразуются в `RuntimeException` с сообщениями ORM, если они доступны. Некорректные ID и пустые наборы полей отклоняются через `InvalidArgumentException`.

## Тестирование без ядра Битрикс

Зависимости репозитория можно подменить через прежний конструктор менеджера:

```php
$manager = new HlBlockManager(
    $moduleLoader,
    $blockFinder,
    $entityCompiler
);
```

Порядок и назначение трёх callable-параметров не изменились. Это позволяет тестировать слой без установленного ядра и базы данных.

Запуск отдельного теста:

```bash
cd local
composer test:hlblock
```

Автономные проверки не заменяют интеграционное тестирование на целевой версии ядра Битрикс.
