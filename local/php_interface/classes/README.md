# Классы проекта

Размещайте здесь собственные PHP-классы проекта.

Composer PSR-4 в `local/composer.json` связывает namespace `Project\\` с этим каталогом.

Например:

- файл: `local/php_interface/classes/Services/CatalogService.php`
- namespace: `Project\Services`
- класс: `CatalogService`

После добавления или изменения классов обновите автозагрузку:

```bash
composer dump-autoload --working-dir=local
```

## Базовые классы Support

### `Project\Support\EmailValidator`

Проверяет синтаксическую корректность email через `FILTER_VALIDATE_EMAIL`. Удаляет пробелы по краям перед проверкой, но не изменяет переданную переменную и не возвращает нормализованный адрес.

Не выполняет DNS-проверку и не подтверждает существование почтового ящика.

```php
$validator = new \Project\Support\EmailValidator();
$email = trim($email);

if ($validator->isValid($email)) {
    // Адрес имеет допустимый формат.
}
```

### `Project\Support\HtmlEscaper`

Кодирует специальные HTML-символы для вывода в HTML-текст или атрибут в кавычках.
Это контекстное кодирование при выводе, а не универсальная очистка входных данных.

```php
$escaper = new \Project\Support\HtmlEscaper();

echo $escaper->escape($name);
```

### `Project\Support\TextPreview`

Преобразует HTML или обычный текст в компактный текстовый анонс. По умолчанию ограничивает результат 300 символами, старается не обрезать последнее слово и добавляет многоточие только при фактическом сокращении.

Длина считается в Unicode-графемах, поэтому кириллица и составные символы не разрезаются посреди байтов или символа.

```php
$preview = new \Project\Support\TextPreview();

echo $preview->create($element['PREVIEW_TEXT'], 200);
```

Класс не зависит от API Битрикс и не требует расширения mbstring.

## Замена старого `Ns\Bitrix\Helper\IBlock\Validator`

Старый класс не переносится как отдельный `IBlock\Validator`: он не выполнял операций с инфоблоками и содержал две несвязанные задачи:

- `email()` — проверка формата email с возвратом адреса или `false`;
- `xss()` — `trim()` и `htmlspecialchars()` для входного значения.

Эти задачи разделены между `EmailValidator` и `HtmlEscaper`. Интерфейс намеренно не копируется один в один: валидатор возвращает `bool`, а HTML-кодирование выполняется в месте вывода, с учётом контекста. Не следует сохранять HTML-сущности в базе вместо исходного текста только ради защиты от XSS.

## Bitrix: работа с элементами инфоблока

Для проверки элементов используйте `Project\Bitrix\IBlock\ElementChecker` (D7 ORM) или `Project\Bitrix\IBlock\LegacyElementChecker` (старый API).

Для генерации символьного кода используйте `Project\Bitrix\IBlock\ElementCodeGenerator`. Для сокращения текста подходит независимый от Битрикс `Project\Support\TextPreview`.

Подробности и примеры приведены в [Bitrix/IBlock/README.md](Bitrix/IBlock/README.md).

### `Project\Support\FlashMessageBag`

Хранит flash-сообщения в переданном массиве сессионного хранилища. Не запускает сессию самостоятельно, не формирует HTML и не экранирует сообщения.

```php
$messages = new \Project\Support\FlashMessageBag($_SESSION);

$messages->add('Изменения сохранены');

// Получить и удалить сообщения:
foreach ($messages->pull() as $message) {
    echo $escaper->escape($message);
}
```

Для повторного чтения без удаления используйте `all()`, для очистки — `clear()`. Экранируйте сообщения при выводе в соответствии с HTML-контекстом.

### `Project\Support\PagePagination`

Рассчитывает состояние постраничной навигации независимо от HTTP-запроса, URL и HTML-шаблона. Текущая страница автоматически ограничивается допустимым диапазоном.

```php
$pagination = new \Project\Support\PagePagination(
    totalItems: 137,
    perPage: 20,
    currentPage: 3
);

$offset = $pagination->getOffset(); // 40
$limit = $pagination->getLimit();   // 20

foreach ($pagination->getPageItems() as $page) {
    if ($page === '…') {
        // Вывести многоточие.
        continue;
    }

    // Самостоятельно сформировать URL и HTML-ссылку на страницу.
}
```

Доступны методы получения общего количества страниц, текущей страницы, смещения, лимита, предыдущей и следующей страниц. `getPageItems($sidePages)` возвращает номера страниц и маркеры пропущенных диапазонов. Класс не читает `$_GET`, не формирует URL и не выводит HTML — это оставлено компоненту или шаблону.

### `Project\Support\PasswordGenerator`

Генерирует пароль заданной длины с помощью `random_int()`. По умолчанию сохранён набор символов старой функции `generate_pass()`, чтобы не менять допустимый алфавит при миграции. Для нулевой или отрицательной длины возвращается пустая строка; пустой алфавит при положительной длине приводит к исключению.

```php
$password = \Project\Support\PasswordGenerator::generate(8);
```

Для постепенной миграции предусмотрен необязательный адаптер `local/php_interface/legacy/password_generator.php`. Подключать его нужно после Composer autoload. Он сохраняет глобальную функцию `generate_pass($number)`, но не воспроизводит побочный эффект старого файла с присваиванием переменной `$newpass`.

#### Совместимость со старым `Ns\Bitrix\Helper\IBlock\Pagination`

Новый `Project\Support\PagePagination` не является drop-in заменой старого класса. Старый API использовал цепочку `perPage()`, `itemsQuantity()`, `withCurrentPage()` и `render()`, а также формировал URL и HTML конкретного сайта.

В старом репозитории найдено только подключение класса через `IBlock::useVariant('pagination')`; фактических вызовов этого варианта в доступном коде не обнаружено. Поэтому старый интерфейс не эмулируется автоматически. При миграции внешнего кода нужно отдельно реализовать адаптер, который сохранит старые методы и URL-контракт. Расчёты страниц можно делегировать `PagePagination`, а HTML и URL формировать в адаптере.
### `Project\Support\ArrayPaginator`

Переработка `Lema\Base\Paginator` из `lema.lib`. Сохраняет основные публичные методы старого класса: `get()`, `setElements()`, `setPerPage()`, `setPagerName()`, `getPageNumber()`, `start()`, `getElements()`, `incCss()` и `getLinks()`.

Исправлены ошибки старой реализации: выбранная страница теперь применяется к `getElements()` после `start()`; размер страницы не может быть нулевым или отрицательным; номер страницы ограничивается доступным диапазоном; заголовок и URL экранируются при генерации HTML. `renderLinks()` возвращает HTML строкой, а старый `getLinks()` по-прежнему выводит его.

Для постепенной миграции есть необязательный адаптер `local/php_interface/legacy/Lema/Base/Paginator.php`. Подключать его нужно после Composer autoload. Он предоставляет старое имя `Lema\Base\Paginator`, не регистрируя namespace глобально. Это позволяет не конфликтовать с установленным `lema.lib` до момента его отключения.

Пример нового API:

```php
$paginator = new \Project\Support\ArrayPaginator($items, 20);
$paginator->start(count($items));
$pageItems = $paginator->getElements();
```

Совместимый вызов после подключения адаптера:

```php
$paginator = \Lema\Base\Paginator::get();
$paginator->setElements($items)->setPerPage(20)->start(count($items));
$pageItems = $paginator->getElements();
```

Вызов `getLinks()` по-прежнему требует контекст Bitrix с глобальным `$APPLICATION`. Самостоятельный расчёт и выбор элементов от Bitrix не зависят.