# Аудит административного indi_data_import

**Источник:** ASDAFF/bitrix.quickstart, ветка master  
**Дата:** 2026-11-20  
**Область:** admin endpoints, upload flow, импортный класс и JS. Код не запускался.

## 1. indi.main/admin/indi_data_import.php

Файл: local/modules/indi.main/admin/indi_data_import.php.

### Порядок выполнения

- Подключается prolog_admin_before.php и проверяется право main module: POST_RIGHT != D.
- При наличии $_FILES['inputFile'] файл перемещается в DOCUMENT_ROOT/upload/upload{time()}.{extension}.
- Имя/расширение берутся из исходного имени файла; allowlist расширений, MIME/content validation и лимит размера в этом endpoint не найдены.
- Путь записывается в $_SESSION['uploadfile']; перед move_uploaded_file() выполняется file_get_contents($uploadFile), то есть чтение ещё не существующего целевого файла. В $_SESSION['xml_uploadfile'] сохраняется false.
- Import::importRequsetManage($_REQUEST) вызывается до check_bitrix_sessid().
- Только после вызова import manager проверяется sessid и выводится результат.
- Загруженный файл остаётся в публичном DOCUMENT_ROOT/upload; cleanup/unlink в endpoint и Import class не найден.

**Риски:**
- CSRF-проверка выполняется после side effects: upload уже перемещён, а Import manager уже вызван.
- При успешном CSRF upload файл с произвольным расширением остаётся в web-accessible /upload; возможны раскрытие содержимого и stored XSS при открытии HTML/SVG.
- Нет лимита размера и безопасного имени/расширения.
- Коллизии имени при нескольких загрузках в одну секунду.
- Ошибка порядка file_get_contents/move_uploaded_file приводит к неверному значению xml_uploadfile.

## 2. Import class — демонстрационный, а не рабочий импорт

Файл: local/modules/indi.main/lib/import.php.

- importRequsetManage() получает service, sessid, lastIndex, limit из request.
- При отсутствии сессионного объекта вызывает getImportObject() и записывает request sessid в $_SESSION[$service]['sessid']; это не независимая CSRF-проверка.
- getProducts() читает файл из $_SESSION['uploadfile']; для XML вызывает XmlToArray::createArray(), для XLS — Util::excelToArray().
- importNew() и importProduct() являются пустыми заглушками; реального добавления новостей/товаров в этих методах нет.
- getNews() содержит демонстрационный массив.
- В обработке нет ограничения размера/количества XML-элементов и безопасных parser flags; XML загружается через DOMDocument::loadXML().

### Stored XSS в административном прогрессе

- Значение Наименование/NAME из импортируемого XML вставляется в JSON-ответ как часть strCurrentAction.
- JS в install/js/indi_data_import/script.js присваивает это значение в oCell.innerHTML без escaping.
- JSON кодируется без JSON_HEX_TAG/JSON_HEX_APOS/JSON_HEX_QUOT.

Если импортируемый XML содержит HTML/JS в названии товара, он может выполниться в браузере администратора на странице импорта.

## 3. site.main/admin/indi_data_import.php — отсутствующий класс

Файл: local/modules/site.main/admin/indi_data_import.php.

- Endpoint вызывает Site\Main\Import::importRequsetManage().
- В Git tree не найден класс Site\Main\Import и файл local/modules/site.main/lib/import.php.
- Следовательно, admin endpoint с этой реализацией выглядит незавершённым и может завершаться fatal error при вызове import action.
- Он также вызывает Import::importRequsetManage() до check_bitrix_sessid().

## 4. Коллизия двух admin файлов

Оба модуля копируют install/admin/indi_data_import.php в /bitrix/admin/indi_data_import.php с rewrite=false. Первый установленный модуль сохраняет файл; второй не заменяет его. В результате фактическая admin-страница зависит от порядка установки:
- indi.main version использует существующий Indi\Main\Import;
- site.main version ссылается на отсутствующий Site\Main\Import.

Это дополняет конфликт /ajax/index.php и /local/templates/main, описанный в MVC audit.

## 5. Решение

1. Перенести check_bitrix_sessid() до загрузки и до любого импорта.
2. Проверять POST, права администратора и конкретное право на импорт до обработки файла.
3. Разрешать только XML/XLS по allowlist, проверять MIME/содержимое, upload error и максимальный размер.
4. Хранить исходный файл вне document root с UUID-именем; после импорта гарантированно удалять.
5. Исправить порядок file_get_contents/move_uploaded_file и убрать неиспользуемый xml_uploadfile.
6. В JS выводить статус через textContent, не innerHTML; либо экранировать серверное значение и JSON_HEX_*.
7. Реализовать importProduct/importNew либо удалить незавершённый admin endpoint.
8. Развести пути admin pages двух модулей и не копировать разные реализации в один target с rewrite=false.

**Итог:** indi.main import page — незавершённый демонстрационный импорт с upload до CSRF check, сохранением файла в публичный /upload и XSS в admin progress UI через XML name. site.main version ссылается на отсутствующий Import class. Оба файла конфликтуют при установке.
