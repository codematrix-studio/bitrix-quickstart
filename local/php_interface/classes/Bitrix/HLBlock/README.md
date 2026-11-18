# Работа с Highload-блоками

В каталоге реализован небольшой слой над D7 ORM для Highload-блоков:

- `HlBlockManager` — подключает модуль `highloadblock`, получает метаданные блока, компилирует ORM-сущность и кеширует её в пределах экземпляра менеджера;
- `HlBlockEntity` — предоставляет операции чтения и изменения записей конкретного блока.

Классы находятся в namespace `Project\Bitrix\HLBlock` и не подключают модуль Битрикс при загрузке PHP-файлов.

## Получение сущности

Для работы используется числовой ID HL-блока. Поле `NAME` не применяется в качестве технического идентификатора: это название, которое не гарантирует уникальность.

```php
use Project\Bitrix\HLBlock\\HlBlockManager;

$manager = new HlBlockManager();
$records = $manager->getById(7);

$items = $records->getList([
    'select' => ['ID', 'UF_XML_ID', 'UF_NAME'],
    'filter' => ['=UF_ACTIVE' => 1],
    'order' => ['ID' => 'DESC'],
]);

$record = $records->getById(15);
```

Менеджер кеширует полученную ORM-обёртку внутри своего экземпляра. Если создать новый менеджер, метаданные и сущность будут запрошены повторно.

## Добавление, изменение и удаление

```php
$recordId = $records->add([
    'UF_XML_ID' => 'external-123',
    'UF_NAME' => 'Новая запись',
]);

$records->update($recordId, [
    'UF_NAME' => 'Изменённая запись',
]);

$records->delete($recordId);
```

Методы `add()`, `update()` и `delete()` проверяют результат D7 ORM. При неуспешной операции выбрасывается `RuntimeException` с сообщениями ORM, если они доступны.

## Поиск по внешнему идентификатору

Универсальный метод для `UF_XML_ID` намеренно не добавлен: не каждый HL-блок обязан иметь такое поле, а формат фильтра и выборки зависит от конкретной схемы.

Используйте стандартный фильтр ORM:

```php
$items = $records->getList([
    'select' => ['ID', 'UF_XML_ID', 'UF_NAME'],
    'filter' => ['=UF_XML_ID' => 'external-123'],
    'limit' => 1,
]);

$record = $items[0] ?? null;
```

## API

| Метод | Результат |
|---|---|
| `HlBlockManager::getById(int $id)` | `HlBlockEntity` для указанного HL-блока |
| `HlBlockEntity::getList(array $parameters = [])` | Массив записей |
| `HlBlockEntity::getById(int $id, array $select = ['*'])` | Запись или `null` |
| `HlBlockEntity::add(array $fields)` | ID новой записи |
| `HlBlockEntity::update(int $id, array $fields)` | `true` при успехе |
| `HlBlockEntity::delete(int $id)` | `true` при успехе |

Некорректные ID и пустые наборы полей для записи отклоняются через `InvalidArgumentException`. Ошибки подключения модуля, поиска/компиляции сущности и операций ORM приводят к `RuntimeException`.

## Автономные тесты

Тесты не требуют установленного ядра Битрикс:

```bash
cd local
composer test:hlblock
```

Проверяются кеширование сущности, подключение модуля, поиск записей, CRUD и обработка ошибок. Эти проверки не заменяют интеграционный тест на установленном Битрикс с реальным HL-блоком.
