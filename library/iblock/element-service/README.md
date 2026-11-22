# IBlock Element Service

Статус: Draft.

## Назначение

Сервис доступа к элементам инфоблока на D7 с отдельными адаптерами для legacy API.

## Реализация

Используется существующий класс Project\Bitrix\IBlock\ElementRepository:
local/php_interface/classes/Bitrix/IBlock/ElementRepository.php.

Он предоставляет getById(), getListD7(), getAllD7(), getCount(), а также legacy-методы getList(), getListPagination() и getAll().

## Пример

    use Project\Bitrix\IBlock\ElementRepository;

    $elements = new ElementRepository();
    $rows = $elements->getListD7(12, [
        'select' => ['ID', 'NAME', 'CODE'],
        'filter' => ['=CODE' => 'example'],
        'order' => ['SORT' => 'ASC'],
        'limit' => 50,
    ]);

getListD7() принудительно задаёт ID инфоблока и ACTIVE=Y. getAllD7() ограничивает инфоблок, но не навязывает активность. Результаты индексируются по ID.

## Граница ответственности и безопасность

Репозиторий — слой доступа к данным, а не авторизации. Он не знает текущего пользователя и не проверяет права на чтение/изменение конкретного элемента. До вызова методов в пользовательском endpoint необходимо проверить доступ к инфоблоку и каждому целевому объекту средствами проекта/БУС.

Не передавайте клиентский фильтр ORM напрямую. Формируйте фильтр на сервере, ограничивайте поля и сортировку allowlist-ом, а ID инфоблока берите из доверенной конфигурации или проверенного контекста.

Модуль iblock подключается перед запросом. При отсутствии модуля выбрасывается RuntimeException.

## Тестирование

Из каталога local:

    composer test:iblock

Тесты проверяют построение D7/legacy-параметров и формат результатов с callback-заглушками. Они не доказывают совместимость с конкретной версией ядра БУС.

## Требования и статус

- PHP: 8.1+
- Модуль: iblock
- Новые вызовы: D7 ORM; legacy-методы помечены отдельно
- Автономные smoke-тесты: tests/lema_iblock_smoke.php и tests/lema_iblock_checker_smoke.php
- Интеграционная проверка на реальном ядре: не выполнялась
- Статус: Draft до Bitrix-интеграционной проверки и ревью контрактов
