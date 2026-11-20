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


## 8. Cart quick-add endpoints

Файлы:
- local/ajax/cart/addtocart.php
- local/ajax/cart/cart.js
- local/ajax/cart/basket_line.php
- local/ajax/ajax.js

- cart.js вызывает /local/ajax/cart/addtocart.php с POST, передавая id; endpoint читает $_REQUEST и вызывает Add2BasketByProductID(id, 1, array("DELAY" => "Y")).
- Нет check_bitrix_sessid(), проверки HTTP-метода, авторизации или rate limit. Это CSRF для добавления произвольного товара в отложенную корзину текущей сессии.
- ID товара не приводится к положительному int до вызова Add2BasketByProductID; проверку существования/доступности товара должен обеспечить API Bitrix.
- cart.js также вызывает /local/ajax/delforbasket.php, но этого файла в Git tree нет.
- ajax.js вызывает /cart/basket_line.php; в Git есть local/ajax/cart/basket_line.php, но корневого /cart/basket_line.php и URL rewrite для него в tree не найдено. Нельзя подтвердить, что этот URL ведёт на checked-in handler.

**Решение:** использовать POST + sessid; проверять положительный ID и доступность товара; восстановить/удалить отсутствующие endpoint references; проверить URL mapping на VM.


## 9. AJAX-подписка в local/ajax/subscribe

Файлы:
- local/ajax/subscribe/subscribe.php
- local/ajax/subscribe/script.js

Подтверждённая цепочка: script.js вызывает endpoint через $.ajax без указания type; jQuery использует GET. В запрос передаётся mail.

Находки:
- NEED_AUTH=true требует авторизованную сессию, но check_bitrix_sessid() отсутствует.
- NOT_CHECK_PERMISSIONS=true отключает обычную проверку прав; endpoint сам не проверяет, что email относится к текущему пользователю.
- GET-параметр mail записывается в активный элемент IBLOCK_ID=3; нет check_email(), нормализации и проверки дубля.
- CSale/subscribe module не используется: это отдельная запись в инфоблок, не стандартная подтверждаемая подписка.
- В почтовые поля передаётся $name, но переменная не определена; фактический MAIL в событии будет пустым.
- Нет rate limit и идемпотентности.

**Оценка:** CSRF/data integrity для авторизованных пользователей и спам активного инфоблока.

**Действие:** POST-only + sessid; нормализовать/валидировать email; уникальный ключ; подтверждение адреса; брать email текущего пользователя, если это личная подписка.

## 10. Бонусная карта: расхождение component namespace

Файл local/components/catalog/bonus_card/component.php обновляет UF_CARD текущего пользователя по $_REQUEST['card'] и отправляет CARD_ADD без POST/sessid; компонент допускает GET и не валидирует номер карты. Если он размещён на доступной авторизованной странице, возможна CSRF-подмена номера карты и повторная отправка уведомлений.

Однако personal/bonus/index.php вызывает devteam:bonus_card, а не catalog:bonus_card. Компонента devteam:bonus_card в текущем Git tree нет. Поэтому local/components/catalog/bonus_card не подтверждён как используемый на этой странице; требуется проверить реальный компонент на сервере.


## 11. Altasib feedback.form: upload policy and reCAPTCHA configuration

Файл: local/components/form/feedback.form/component.php.

Положительные проверки:
- Обработка формы требует POST и check_bitrix_sessid().
- Есть проверка обязательных полей и системной CAPTCHA либо reCAPTCHA.

Находки:
- FILE_TYPE проверяется только по расширению из имени файла; если настройка FILE_TYPE у свойства инфоблока пустая, принимается файл с любым расширением.
- MIME/content и максимальный размер файла в этом компоненте не проверяются.
- Файл передаётся в CFile как свойство инфоблока; риск выполнения PHP зависит от защиты /upload, но HTML/SVG файл может стать stored-content/XSS vector, если выдаётся браузеру inline.
- В reCAPTCHA ветке присваивание $site_key/$server_key закомментировано, а далее используется $server_key; режим reCAPTCHA выглядит неработоспособным.
- CURLOPT_TIMEOUT установлен в 1 секунду; нет полноценной проверки HTTP status и структуры ответа до чтения success/error-codes.
- Rate limit не найден.

Поиск вызовов компонента feedback.form в текущем Git tree не выявил; реальное использование/настройки FILE_TYPE и ACTIVE_ELEMENT нужно проверить на VM.

**Действие:** обязательный allowlist расширений, MIME/content validation, размер, случайное имя и хранение вне web root; проверять reCAPTCHA secret из защищённой конфигурации; добавить rate limit.
