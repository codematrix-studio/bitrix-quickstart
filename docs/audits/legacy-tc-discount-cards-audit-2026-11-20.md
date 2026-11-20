# Аудит модуля tc и импорта дисконтных карт

**Источник:** ASDAFF/bitrix.quickstart, ветка master  
**Дата:** 2026-11-20  
**Область:** local/modules/tc, local/components/catalog/card.import.1c, local/components/catalog/bonus_card, call sites. Код не запускался.

## 1. tcCards: SQL injection и опасные операции

Файл: local/modules/tc/classes/general/cards.php.

### Add()

- VLADELEC и TIP_SKIDKI проходят через DB::ForSql().
- OSTATOK и PROCENT приводятся к int.
- SUMMA фильтруется numValidate().
- NOMER вставляется в SQL без ForSql/параметризации:
  INSERT ... VALUES (..., '{$arr['NOMER']}', ...).
- Вызов Add() из card.import.1c передаёт NOMER напрямую из XML-поля НомерКарты.

**Риск:** SQL injection через номер карты при обработке импортируемого XML. С учётом отключённых auth/group checks в card.import.1c это критический риск, если endpoint доступен извне.

### GetByNum()

Выполняет SQL вида SELECT ... WHERE nomer = $id, где $id не приводится к int и не экранируется. Если UF_CARD содержит управляемое пользователем значение, возможно SQL injection.

Найденные места использования:
- local/components/catalog/bonus_card/component.php;
- local/php_interface/classes/OnBeforeBasketAdd.php.

Но:
- local/components/catalog/bonus_card не найден как вызываемый component include;
- personal/bonus/index.php вызывает devteam:bonus_card, которого в Git tree нет;
- OnBeforeBasketAdd class не найден зарегистрированным в event handlers.

Поэтому этот путь SQLi зафиксирован в классе, но активный call site не подтверждён.

### GetList()

- Имена колонок ограничиваются allowlist.
- Направление сортировки нормализуется к asc/desc.
- Значения фильтров для vladelec, tipsidki, id, nomer и других полей вставляются в SQL без ForSql.
- Текущий admin/cards_search.php передаёт пустой filter, поэтому конкретный административный вызов не подтверждает эксплуатацию фильтра через request.

### RemoveAll()

Выполняет DELETE FROM cards без WHERE. Вызывается из card.import.1c на шаге 3.

## 2. card.import.1c: удаление всей базы карт и импорт XML

Файл: local/components/catalog/card.import.1c/component.php.

- Проверки mode=checkauth, авторизации и GROUP_PERMISSIONS отключены через 0 &&.
- check_bitrix_sessid() в компоненте не найден.
- На шаге 3 после обработки временных таблиц вызывается tcCards::RemoveAll(); результат импорта не ограничивает этот вызов.
- На шаге 4 записи восстанавливаются из b_xml_tree через tcCards::Add(), включая raw XML value НомерКарты.
- Нет транзакции/backup/rollback; при ошибке XML или Add все существующие карты уже удалены.
- Прямых вызовов catalog:card.import.1c в Git tree не найдено; web-доступность требует проверки.

**Оценка:** при доступном endpoint — unauthenticated destructive import + SQL injection через NOMER + полная потеря таблицы cards при неполном импорте.

## 3. Модуль tc: installer не создаёт таблицу cards

Файлы:
- local/modules/tc/install/index.php
- local/modules/tc/install/db/mysql/install.sql
- local/modules/tc/install/db/mysql/uninstall.sql
- local/modules/tc/classes/general/cards.php

- InstallDB() и UnInstallDB() возвращают true без запуска SQL batch.
- install.sql создаёт таблицы b_cs_property_types, b_cs_properties и b_cs_property_values.
- tcCards использует таблицу cards; её CREATE TABLE в install.sql отсутствует (есть только комментарий с примером схемы в cards.php).
- uninstall.sql удаляет только b_cs_* таблицы, не cards.
- Следовательно, штатная установка модуля не создаёт таблицу, которую использует tcCards; она могла быть создана вручную/вне репозитория.

## 4. Бонусная карта component и endpoint

Файлы:
- local/components/catalog/bonus_card/component.php
- personal/bonus/index.php

- Local component принимает card через REQUEST и обновляет UF_CARD текущего пользователя; нет POST-only/check_bitrix_sessid/валидации номера.
- При каждом таком запросе отправляет CARD_ADD; rate limit/idempotency нет.
- Но personal/bonus/index.php вызывает devteam:bonus_card, а не catalog:bonus_card; devteam component в Git tree отсутствует.
- Нельзя считать local/components/catalog/bonus_card реально используемым без проверки deployment.

## 5. OnBeforeBasketAdd

Файл local/php_interface/classes/OnBeforeBasketAdd.php вызывает tcCards::GetByNum($arUser['UF_CARD']) при UF_CARD_MODERATED, но регистрация этого обработчика в текущем дереве не найдена. Не считать активным без проверки handlers.php/DB event registrations на VM.

## 6. Решение

- Немедленно заменить raw SQL в Add/GetByNum/GetList на параметризованные запросы/ORM.
- Валидировать номер карты как строго заданный формат (например, только цифры установленной длины) на сервере, а не только в форме.
- Запретить публичный импорт card.import.1c; восстановить auth/group/sessid checks.
- Удалять старые карты только после успешного парсинга и проверки полного набора новых записей; выполнять replace в транзакции/через staging table.
- Исправить tc installer: создать фактическую таблицу cards, проверять результаты SQL, мигрировать существующую таблицу.
- Перед любыми изменениями сделать backup cards и b_cs_*; не запускать uninstall SQL вручную.

**Итог:** в tcCards есть подтверждённые raw SQL места, а card.import.1c сочетает выключенные проверки доступа, DELETE FROM cards без WHERE и импортируемый NOMER без escaping. Активность endpoints и фактическая схема БД проверяются на VM.
