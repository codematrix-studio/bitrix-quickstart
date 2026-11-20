# Аудит MVC AJAX router в site.main и indi.main

**Источник:** ASDAFF/bitrix.quickstart, ветка master  
**Дата:** 2026-11-20  
**Область:** root AJAX routers и контроллеры site.main / indi.main. Код не запускался.

## 1. Общая архитектура и границы доступа

Оба модуля устанавливают файл install/doc_root/ajax/index.php в корень сайта как /ajax/index.php.

Маршрут: /ajax/{controller}/{action}/

В обоих router:
- подключается prolog_before.php;
- загружается соответствующий модуль;
- имя контроллера берётся из URL и передаётся в Prototype::factory();
- имя action берётся из URL и вызывается через doAction().

В Prototype:
- нет общей проверки авторизации;
- нет проверки HTTP-метода;
- нет check_bitrix_sessid()/CSRF;
- нет allowlist контроллеров/actions — используется class_exists() и method_exists();
- catch обрабатывает только Exception, не Throwable;
- сообщение исключения напрямую выводится клиенту.

Безопасность полностью переложена на каждый action. В ряде actions проверок нет.

## 2. Критическая уязвимость: site.main User controller

Файл: local/modules/site.main/lib/mvc/controller/user.php.

### updateUInfoAction()

- Принимает USER_ID, ACTION и остальные поля из request.
- Нет проверки $USER->IsAuthorized(), группы администратора или права на редактирование целевого пользователя.
- Нет CSRF/sessid.
- Разрешённые поля включают EMAIL, PERSONAL_PHONE, PERSONAL_CITY, UF_RATING, UF_USER_TYPE, NEW_PASSWORD.
- При ACTION=UPDATE_ALL можно передать свой NEW_PASSWORD; метод записывает его как PASSWORD и CONFIRM_PASSWORD для указанного USER_ID.
- При ACTION=BLOCK_USER можно менять ACTIVE указанного пользователя на значение из request.

**Оценка:** критический IDOR/account takeover/privilege abuse. При доступном маршруте атакующий может менять пароль, email, пользовательский тип и блокировать/разблокировать произвольную учётную запись.

### changePasswordAction()

- Принимает USER_EMAIL из request, ищет пользователя по LOGIN и сбрасывает пароль на randString(6).
- Нет проверки авторизации, подтверждения владения email, sessid/CSRF и rate limit.
- В коде нет отправки нового пароля на email, хотя сообщение клиенту утверждает обратное.

**Оценка:** любой вызывающий может инициировать сброс пароля произвольной учётной записи и заблокировать владельцу вход; также возможна проверка существования login/email.

## 3. Критические уязвимости: indi.main Images controller

Файл: local/modules/indi.main/lib/mvc/controller/images.php.

Подтверждённые вызовы: local/modules/indi.main/install/templates/main/js/template.js вызывает:
- /ajax/images/saveUploadImage;
- /ajax/images/deleteImage;
- /ajax/images/cropImage/;
- /ajax/images/saveUserPhoto/.

В JS нет передачи sessid.

### Actions

- saveUploadImageAction(): принимает файл и folder из request; проверяет только CFile::IsImage($arIMAGE['name']), затем сохраняет файл. Нет auth/CSRF, upload error/size/content validation, allowlist folder.
- deleteImageAction(): вызывает CFile::Delete() с ID из request без проверки владельца файла.
- cropImageAction(): берёт путь DOCUMENT_ROOT . $_REQUEST['img'], открывает его через Imagick и записывает обратно; нет path containment/allowlist, нет auth/CSRF; затем удаляет oldImageId из request без проверки владельца.
- saveUserPhotoAction(): принимает userId и photoId, загружает пользователя по произвольному ID и вызывает CUser::Update(); нет проверки, что пользователь совпадает с текущим, нет auth/CSRF.

**Оценка:** критический набор IDOR/unrestricted file operations. Возможны удаление чужих файлов, замена фото произвольного пользователя и изменение/перезапись изображения по пути, переданному клиентом. Для cropImage требуется проверить ограничения Imagick и файловые права на VM.

## 4. indi.main Basket controller

Файл: local/modules/indi.main/lib/mvc/controller/basket.php.

- addAction() и editAction() добавляют товар в корзину текущего FUSER через Indi\Main\Basket; нет CSRF/method check.
- editAction() фактически вызывает add(), а не изменяет существующую позицию.
- removeAction() вызывает CSaleBasket::Delete($id) по ID из request без фильтра текущего FUSER/LID.
- updateCountAction() вызывает CSaleBasket::Update($id, QUANTITY) по ID без фильтра текущего FUSER/LID/ORDER_ID.
- getpopupAction() собирает HTML из имени товара/артикула и возвращает его как HTML без явного escaping; нужно проверить escaping в PHP view add-basket/popup.php.
- Внутренние вызовы этих actions в Git tree не найдены.

**Оценка:** IDOR в remove/update при прямой доступности маршрута; CSRF для добавления/изменения корзины. Требуется проверять владельца записи и исключать позиции с ORDER_ID.

## 5. site.main Packets controller

Файл: local/modules/site.main/lib/mvc/controller/packets.php.

updatePInfoAction():
- PASS проверяет, что UF_USER пакета совпадает с текущим user ID — это правильная проверка владельца.
- CONFIRM_UPLOAD, CONFIRM и CANCEL проверяют наличие GROUP_ID_PORTAL_ADMIN.
- Однако нет CSRF/sessid и ограничения HTTP-метода. Поскольку router принимает действия без общей CSRF-проверки, авторизованного владельца пакета/администратора можно заставить выполнить соответствующее действие.
- PASS загружает файл в S3 с ACL=public-read, MIME фиксирован как text/plain; нет проверки размера/содержимого/upload error.
- Админские действия перемещают/удаляют объекты S3, меняют статус пакета и рейтинг пользователя, отправляют письма.

getPacketAction() проверяет авторизацию, но нет CSRF; выдаёт свободный пакет и привязывает к пользователю. Есть race window между getFreePacket() и linkPacketToUser(); после sleep проверяется владелец, но при гонке возможны повторные попытки/потеря доступности.

getCancelReasonsAction() не проверяет авторизацию; выводит HTML-шаблон по переданному PACKET_ID.

**Оценка:** CSRF на действия владельца и администратора; гонка при выдаче пакета.

## 6. site.main Grids controller

Файл: local/modules/site.main/lib/mvc/controller/grids.php.

- updatePInfoAction() меняет статусы пакетов, файлы в S3, рейтинг пользователей и отправляет уведомления.
- Нет проверки авторизации, роли администратора, владельца пакета или CSRF.
- Принимает PACKET_ID и ACTION из request; в ветке загрузки использует имя файла из $_FILES без проверки размера/типа.
- Дополнительно использует Amazon::$arConfig, хотя в классе Site\Main\Amazon определён метод getConfig(), а статическое свойство $arConfig в проверенной реализации не найдено. Это может приводить к ошибке до выполнения действия; runtime требует проверки.

Поиск вызовов /ajax/grids/updatePInfo не выявил. Но endpoint доступен через общий router, если модуль установлен.

## 7. site.main Trials controller

Файл: local/modules/site.main/lib/mvc/controller/trials.php.

- Проверяет, что пользователь авторизован.
- Не проверяет sessid/CSRF перед выдачей trial serial.
- Читает DOCUMENT_ROOT/serials4999.csv, берёт первую строку, удаляет её из файла и записывает serial в поле пользователя.
- Нет блокировки файла/атомарного обновления; параллельные запросы могут получить один и тот же serial или потерять строку.
- preg_split('/[(\r)?\n]/mi', ...) использует character class, а не корректный разделитель строк; символы (, ), ? внутри serial могут ломать разбор.
- Файл ожидается в корне web-документа; его HTTP-доступность/защита не подтверждена. Если он доступен как статический файл, весь пул serials раскрывается.

**Оценка:** CSRF/гонка и потенциальная утечка серийных ключей. Файла serials4999.csv в Git нет.

## 8. indi.main Form controller

Actions в local/modules/indi.main/lib/mvc/controller/form.php в основном выводят публичные формы Bitrix Web Forms. Прямой записи данных в самом контроллере не обнаружено; отправку и CSRF следует проверять в настройках/шаблонах конкретных Web Forms.

## 9. Использование и ограничения

- Оба модуля копируют install/doc_root в document root; router /ajax/index.php предназначен для публичного HTTP-доступа.
- Подтверждённые клиентские вызовы Images actions найдены в indi.main/install/templates/main/js/template.js.
- Вызовы site.main/user/updateUInfo, changePassword, grids/updatePInfo, packets/updatePInfo, trials/getTrial и indi.main/basket/* в доступном Git tree не найдены; прямой вызов endpoint всё равно возможен при установленном модуле.
- Состояние установки модулей, реальный document root, маршруты и ACL на VM не проверялись.

## Итог

Самые опасные действия — site.main/user/updateUInfoAction (произвольное изменение пользователя/пароля), indi.main/images/* (удаление файлов и изменение фото произвольного пользователя) и indi.main/basket/remove/updateCount (операции по ID без владельца). Общий MVC router не даёт базовой защиты; каждый action должен проверять auth, CSRF, HTTP method и ownership. До исправления не размещать эти endpoints на доступном извне сайте.
