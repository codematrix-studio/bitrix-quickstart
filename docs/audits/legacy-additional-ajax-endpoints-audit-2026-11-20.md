# Аудит дополнительных AJAX endpoints компонентов

**Источник:** `ASDAFF/bitrix.quickstart`, ветка `master`  
**Дата:** 2026-11-20  
**Область:** custom AJAX handlers в `local/components`, `local/ajax/wishlist` и компонентах `lema.lib`. Статический просмотр, без запуска.

## 1. `personal.info`: изменение профиля через GET

Файлы:
- `local/components/personal/personal.info/templates/.default/template.php`
- `script.js`
- `ajax.php`

Подтверждённая цепочка:
1. Форма объявлена как `method="GET"`.
2. JS сериализует все поля и вызывает `$.getJSON(ajaxDir + "/ajax.php?" + $form.serialize())`.
3. Endpoint получает данные из `$_GET`, берёт ID только из текущей авторизованной сессии и вызывает `CUser::Update($userID, $fields)`.

Находки:
- Нет `check_bitrix_sessid()` и другого CSRF-токена.
- Через GET меняются email, имя и профильные поля; при передаче `USER_PASSWORD` и `USER_PASSWORD_CONFIRM` меняется пароль текущего пользователя.
- Это CSRF на изменение профиля и потенциальный account takeover: авторизованного пользователя можно заставить открыть специально сформированный URL, который установит заданный атакующим пароль.
- Пароль передаётся в query string и попадает в browser history, access logs, reverse proxy logs и потенциальные Referer.
- В форме поля пароля имеют `type="text"`, а не `type="password"`.
- Профильные значения из БД выводятся в HTML-атрибуты/textarea без контекстного escaping.
- Ответ JSON собирается вручную через `addslashes()`, а не `json_encode()`; экранирование не соответствует JSON-контексту.
- Разбор ФИО через `explode(" ", ...)` использует индексы 0/1/2 без проверки количества частей.

**Приоритет:** критический, если компонент размещён на доступной странице.

**Действие:** немедленно отключить endpoint/форму до исправления; заменить GET на POST, проверять sessid до обновления, не принимать пароль через URL, использовать password input, серверную валидацию, JSON через `json_encode()` и escaping шаблона.

## 2. Wishlist endpoints с подменой пользователя

Файлы:
- `local/ajax/wishlist/ajax_sestrenskyi_wish.php`
- `local/ajax/wishlist/ajax_sestrenskyi_wishList_del.php`

- Оба принимают `idUser` из POST и используют его напрямую как `UF_USER`.
- Не проверяют авторизацию и не сверяют `idUser` с `$USER->GetID()`.
- Не проверяют sessid/CSRF и не ограничивают HTTP-метод.
- Add создаёт запись без проверки дубля; delete удаляет все совпадения по переданным `idUser` и `idProduct`.
- Поиск вызовов этих файлов в доступном Git-дереве не выявил.

**Оценка:** IDOR — при прямой доступности можно добавлять/удалять записи wishlist от имени другого пользователя, если известны его ID и ID товара.

**Действие:** использовать только ID текущей сессии, добавить CSRF, проверку существования товара и уникальность пары user/product.

## 3. `helper.ajaxform`: отправка письма на адрес из запроса

Файлы:
- `local/components/helper/ajaxform/templates/.default/template.php`
- `script.js`
- `ajax.php`

Подтверждённая цепочка: template передаёт `EMAIL_TO` в JS, JS отправляет его вместе с полями формы на `ajax.php`.

Находки:
- Endpoint принимает `EMAIL_TO` из POST и передаёт его напрямую в `mail()`.
- Нет проверки email, allowlist адресов, sessid/CSRF, rate limit, CAPTCHA или ограничения частоты.
- Имя, телефон и текст сообщения конкатенируются без ограничения длины.
- Любой внешний клиент может попытаться использовать endpoint как почтовый relay/spam gateway, если файл доступен напрямую.
- Результат `mail()` не проверяется.

**Действие:** получателя брать только из серверной конфигурации компонента, никогда не доверять `EMAIL_TO` из запроса; добавить CSRF, rate limit, CAPTCHA и проверку полей.

## 4. `lema:form.ajax`: доверие к arParams от клиента

Файлы:
- `local/modules/lema.lib/install/components/lema/form.ajax/ajax.php`
- `class.php`
- `templates/.default/template.php`
- `templates/.default/script.js`
- `local/modules/lema.lib/lib/Forms/AjaxForm.php`

Подтверждённая цепочка: JS сериализует `arParams` компонента в JSON и отправляет его в POST как `arParams`.

Находки:
- Endpoint не проверяет `check_bitrix_sessid()`, подпись параметров или права.
- `IBLOCK_ID`, `EVENT_TYPE`, `NEED_SEND_EMAIL`, `NEED_SAVE_TO_IBLOCK` и описание полей берутся из клиентского JSON; посетитель может изменить параметры перед отправкой.
- При включённом `NEED_SAVE_TO_IBLOCK` сервер вызывает `CIBlockElement::Add()` для переданного IBLOCK_ID.
- При `NEED_SEND_EMAIL` endpoint проверяет лишь существование event type и вызывает `CEvent::Send()`; allowlist event types и ограничение адресатов отсутствуют.
- Правила валидации также строятся из переданного клиентом `FORM_FIELDS`; их можно изменить/обойти.
- `AjaxForm::addRecord()` не проверяет права на инфоблок, а `sendMessage()` принимает любой event type.
- Класс компонента генерирует HTML-атрибуты из `field[name/type/placeholder]` без контекстного escaping. При недоверенной конфигурации возможен XSS.

**Оценка:** подмена параметров позволяет обойти бизнес-конфигурацию формы, инициировать почтовые события и создавать записи в произвольных инфоблоках.

**Действие:** параметры хранить на сервере; передавать только подписанный идентификатор формы; проверять sessid и разрешения; allowlist IBLOCK_ID/event type; валидировать данные по серверной схеме.

## 5. `form.reviews/ajax.php`: mass assignment

Файлы:
- `local/components/form/form.reviews/component.php`
- `ajax.php`
- `templates/.default/template.php`
- `README.md`

- Component сохраняет параметры формы в `$_SESSION['COMPONENT_FORM']['PARAMS']`.
- AJAX endpoint начинает с IBLOCK_ID/ACTIVE из session, но затем копирует все поля `$_POST` в `$newElementFields`; POST может переопределить IBLOCK_ID, ACTIVE и другие служебные поля.
- Для `PROPERTY_*` имена свойств формируются напрямую из ключей POST.
- Нет CSRF/sessid, CAPTCHA, rate limit или проверки прав на запись.
- Ответ содержит `$arParams` в `result['log']`, что раскрывает конфигурацию формы.
- Встроенный `script.js` не содержит вызова `ajax.php`; README показывает пример компонента, поэтому фактическое использование endpoint не подтверждено.

**Действие:** явный allowlist полей и свойств, IBLOCK_ID/ACTIVE только из server-side session/config, CSRF, права, отсутствие debug-лога.

## 6. `social/emarket.comments/ajax.php`

Файлы:
- `local/components/social/emarket.comments/templates/.default/ajax.php`
- `script.js`
- `template.php`

- JS действительно вызывает endpoint для добавления комментариев и обновления счётчиков.
- Добавление комментария проверяет CAPTCHA, но не CSRF/sessid, не ограничивает частоту и не проверяет доступ к элементу/инфоблоку.
- Ветка `ajax_type=update` CAPTCHA не проверяет; ID записи и поле обновления берутся из request.
- Проверки повторного действия основаны на cookie/session, а не на надёжном серверном ограничении.
- Нет проверки, что выбранная HL-запись относится к тому товару/инфоблоку, который передан в request; пользователь может воздействовать на чужие записи в связанной HL-таблице.
- Шаблон выводит имя и тексты комментариев без escaping. Текущий add handler применяет `strip_tags()+htmlspecialchars()` к строковым полям, но исторические/внешние записи и update-flow должны считаться недоверенными.

**Действие:** server-side allowlist изменяемых полей, привязка HL-записи к product ID, CSRF, CAPTCHA/rate limit для add, контекстное escaping при выводе.

## 7. `tools/component.reload/ajax.php`

- Endpoint подключает prolog с `NOT_CHECK_PERMISSIONS=true`.
- Из request принимает имя компонента, шаблон, параметры, parent component и function params и передаёт их напрямую в `$APPLICATION->IncludeComponent()`.
- Не найдены whitelist компонента/шаблона, подпись параметров, CSRF или проверка авторизации.
- Самостоятельной папки компонента и call site в текущем Git-дереве не найдено.

**Оценка:** опасный generic component invocation endpoint при прямой доступности. До выяснения назначения закрыть web-доступ или удалить из поставки.

## Итог

Подтверждённая цепочка с наибольшим приоритетом — `personal.info`: авторизованный пользователь меняет пароль через GET без CSRF. Дополнительно `helper.ajaxform` принимает адрес получателя из запроса, а `lema:form.ajax` доверяет клиентскому `arParams`. Остальные endpoints имеют IDOR/mass-assignment/CSRF риски; часть call sites в репозитории не найдена, поэтому их фактическая эксплуатация требует проверки на VM.
