# Регистрация ресурсов Bitrix

## AssetManager

`Project\Bitrix\Asset\AssetManager` — небольшой фасад над штатными механизмами регистрации ресурсов Bitrix:

- `Bitrix\Main\Page\Asset::getInstance()->addJs()`;
- `Bitrix\Main\Page\Asset::getInstance()->addCss()`;
- `CJSCore::init()` для зарегистрированных расширений ядра.

Пример:

```php
use Project\Bitrix\Asset\AssetManager;

$assets = new AssetManager();

$assets
    ->addCss([
        SITE_TEMPLATE_PATH . '/css/style.css',
        SITE_TEMPLATE_PATH . '/css/mobile.css',
    ])
    ->init(['jquery2', 'popup'])
    ->addJs(SITE_TEMPLATE_PATH . '/js/scripts.js');
```

Методы `addJs()` и `addCss()` принимают путь к одному файлу или массив путей. Они добавляют ресурсы в очередь Bitrix и возвращают текущий объект для цепочки вызовов. Порядок передачи сохраняется; фактический порядок вывода и дедупликацию определяет ядро Bitrix.

`init()` передаёт список идентификаторов расширений в `CJSCore::init()`. Класс не регистрирует расширения самостоятельно: они должны быть известны CJSCore.

Пути и имена расширений должны быть непустыми строками. Класс не проверяет существование файлов, не переписывает URL и не добавляет ресурсы напрямую в HTML.

## Автономное тестирование

Для тестов можно передать объект с методами `addJs()` и `addCss()`, а также callable для инициализации расширений:

```php
$assets = new AssetManager($fakeAsset, $extensionInitializer);
```

Если зависимости не переданы, используются штатные API Bitrix. При отсутствии ядра или CJSCore выбрасывается `RuntimeException`, а не выполняется молчаливый пропуск регистрации.

## Совместимость с lema.lib

Необязательный адаптер находится в `local/php_interface/legacy/Lema/Common/AssetManager.php`.

Сохранены:

- `addJsArray(array $data = [])`;
- `addCssArray(array $data = [])`;
- `init(array $data = [])`;
- статический `get()` и `resetInstance()` старого singleton-подхода.

Подключение:

```php
require_once __DIR__ . '/legacy/Lema/Common/AssetManager.php';

$assets = \Lema\Common\AssetManager::get();
$assets->addCssArray(['/local/templates/site/style.css']);
```

Адаптер подключается вручную после Composer autoload. Не подключайте его одновременно с исходным модулем `lema.lib`, иначе возникнет конфликт имён классов.

Старый класс `Lema\Common\Asset` не переносится: он только дублировал получение singleton-объекта Bitrix Asset и не добавлял самостоятельного поведения.

## Границы проверки

Автономный smoke test проверяет делегирование, порядок ресурсов, цепочки вызовов, валидацию аргументов и работу без ядра Bitrix через внедрённые зависимости.

Фактический вывод JS/CSS в HTML, дедупликация, поведение `CJSCore` и совместимость с конкретной версией ядра требуют отдельной интеграционной проверки на работающем проекте Bitrix.
