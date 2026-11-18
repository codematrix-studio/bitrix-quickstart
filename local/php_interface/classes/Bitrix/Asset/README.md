# Подключение JavaScript и CSS

## AssetManager

Project\Bitrix\Asset\AssetManager — фасад над штатным объектом Bitrix\Main\Page\Asset. Он не хранит собственный реестр ресурсов: файлы передаются непосредственно в addJs() и addCss().

    use Project\Bitrix\Asset\AssetManager;
    use Bitrix\Main\Page\Asset;

    $assets = new AssetManager(Asset::getInstance());

    $assets
        ->addCss([SITE_TEMPLATE_PATH . '/css/site.css'])
        ->init(['fx', 'jquery2'])
        ->addJs([SITE_TEMPLATE_PATH . '/js/site.js']);

Методы addCss() и addJs() принимают строку или массив строк и возвращают текущий объект. Пути и имена расширений должны быть непустыми строками. Порядок регистрации сохраняется. Пустой массив ничего не добавляет. Существование файлов не проверяется.

Метод init() вызывает CJSCore::init(). Для автономных тестов можно передать callback вторым аргументом конструктора. Если CJSCore недоступен и callback не передан, выбрасывается LogicException.

## Миграция со старого lema.lib

    $assets = new \Lema\Common\AssetManager();
    $assets
        ->addCssArray(['/css/site.css'])
        ->init(['fx', 'jquery2'])
        ->addJsArray(['/js/site.js']);

Адаптеры legacy/Lema/Common/Asset.php и legacy/Lema/Common/AssetManager.php сохраняют:
- старые имена классов;
- конструктор AssetManager без аргументов;
- методы addCssArray(), addJsArray() и init();
- цепочку вызовов;
- статический AssetManager::get(), унаследованный ранее от StaticInstance;
- Asset::get(), возвращающий штатный Bitrix Asset.

Подключайте адаптеры явно после Composer autoload и только после отключения исходного модуля lema.lib:

    require_once __DIR__ . '/legacy/Lema/Common/Asset.php';
    require_once __DIR__ . '/legacy/Lema/Common/AssetManager.php';

Адаптеры не подключаются автоматически и не наследуют старые базовые singleton-классы. Экземпляр AssetManager::get() хранится отдельно для каждого класса-наследника.

## Границы проверки

Smoke-тест проверяет регистрацию ресурсов через тестовый объект, порядок вызовов, fluent API, валидацию, CJSCore и legacy-контракты. Он не подтверждает фактический вывод тегов в HTML.

На установленном ядре Bitrix отдельно проверьте регистрацию файлов в контексте страницы, фактический вывод CSS/JS, доступность расширений CJSCore и совместимость с целевой версией ядра.

Запуск из каталога local: composer test:asset.
