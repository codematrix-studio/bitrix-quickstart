# Аудит basket AJAX copies и фактического пути обработчика

**Источник:** `ASDAFF/bitrix.quickstart`, ветка `master`  
**Дата:** 2026-11-20  
**Область:** корзинные AJAX-копии в `local/components` и `local/modules/mht/sale`, JS вызовы и installer. Код не запускался.

## 1. Подтверждённый клиентский путь

Файл `local/components/cart/sale.basket.basket/templates/.default/script.js` для выбора SKU отправляет POST на:

`/bitrix/components/bitrix/sale.basket.basket/ajax.php`

В запросе передаются `basketItemId`, `props` и `BX.bitrix_sessid()`. Значит, шаблон не вызывает локальный файл `local/components/cart/sale.basket.basket/ajax.php`.

## 2. Дефект в проверенных копиях AJAX

В следующих файлах:
- `local/components/cart/sale.basket.basket/ajax.php`;
- `local/modules/mht/sale/install/components/bitrix/sale.basket.basket/ajax.php`;
- `local/modules/mht/sale_old/install/components/bitrix/sale.basket.basket/ajax.php`;

обработчик:
- проверяет POST и `check_bitrix_sessid()`;
- получает `basketItemId`;
- ищет запись `CSaleBasket::GetList()` только по `ID`;
- не ограничивает выборку `FUSER_ID`, `LID` и `ORDER_ID = NULL`;
- затем обновляет найденную запись через `CSaleBasket::Update($arItem['ID'], $arFields)`.

При доступности именно этой версии обработчика пользователь может подставить ID чужой корзинной позиции и изменить SKU/цену/количество на основе переданных свойств. Отсутствие `ORDER_ID = NULL` также означает, что обработчик не исключает уже привязанные к заказу позиции; фактическое поведение Update для таких записей нужно проверить на версии sale модуля.

Класс корзины в `mht/sale/install/components/bitrix/sale.basket.basket/class.php` при обычном построении списка корректно фильтрует по текущему `FUSER_ID`, `LID` и `ORDER_ID = NULL`. AJAX handler этот фильтр не повторяет.

**Оценка:** IDOR в проверенных исходниках. Но реальная эксплуатация зависит от того, какой файл находится по URL `/bitrix/components/bitrix/sale.basket.basket/ajax.php`.

## 3. Расхождение deployment source

`local/modules/mht/sale/install/index.php::InstallFiles()` копирует компоненты из:

`/bitrix/modules/sale/install/components`

в:

`/bitrix/components`

Он не копирует `local/modules/mht/sale/install/components`. Следовательно, checked-in custom AJAX файл в `local/modules/mht/sale/install/components` не подтверждён как источник установленного endpoint при штатном запуске этого installer.

В репозитории отсутствует исходник `/bitrix/modules/sale/install/components/bitrix/sale.basket.basket/ajax.php` из Bitrix Core. Фактический обработчик по клиентскому URL проверить по Git невозможно.

## 4. Дополнительные копии

- `local/components/cart/sale.basket.basket/ajax.php` имеет ту же выборку только по ID, но собственный JS на него не ссылается.
- `local/components/mht/sale.basket.basket/ajax.php` имеет закомментированные проверки POST и sessid; прямых вызовов в Git tree не найдено.
- `mht/sale_old` содержит старую копию AJAX handler с аналогичным ID-only filter.
- Поиск `cart:sale.basket.basket` и `mht:sale.basket.basket` как component include в Git tree не выявил.

## 5. Решение

1. На VM открыть фактический `/bitrix/components/bitrix/sale.basket.basket/ajax.php` и установить его происхождение/версию.
2. В любом рабочем обработчике выборки использовать одновременно `ID`, текущий `FUSER_ID`, `LID` и `ORDER_ID => null`.
3. Проверять, что товар является offer текущего родительского товара и что набор свойств допустим для этой SKU.
4. Исключить изменение уже заказанных позиций.
5. Добавить журнал отказов и тесты на подмену basketItemId между двумя сессиями и на ID позиции с ORDER_ID.

**Итог:** IDOR подтверждён в трёх checked-in копиях, но не подтверждён в фактически вызываемом core endpoint. Установщик mht/sale копирует другой source path. На VM требуется проверка реального файла и тест между двумя пользователями; сейчас уязвимость работающего сайта не утверждается.
