# Подключение JavaScript и CSS

## AssetManager

Project\\Bitrix\\Asset\\AssetManager — небольшой фасад над штатным объектом Bitrix\\Main\\Page\\Asset. Он не хранит собственный реестр ресурсов и не заменяет механизм Bitrix: все файлы передаются непосредственно в addJs() и addCss().

Пример:

    use Project\\Bitrix\\Asset\\AssetManager;
    use Bitrix\\Main\\Page\\Asset;

    $assets = new AssetManager(Asset::getInstance());

    $assets
        ->addCss([
            SITE_TEMPLATE_PATH . '/css/site.css',
            SITE_TEMPLATE_PATH . '/css/theme.css',
        ])
        ->init(['fx', 'jquery2'])
        ->addJs([SITE_TEMPLATE_PATH . '/js/site.js']);

### API

- __construct(object $asset, ?callable $extensionInitializer = null) — принимает объект Asset и необязательный обработчик инициализации расширений;
- addCss(string|array $files): self — регистрирует CSS-файлы;
- addJs(string|array $files): self — регистрирует JavaScript-файлы;
- init(array $extensions): self — вызывает CJSCore::init() либо переданный обработчик.

Пути и имена расширений должны быть непустыми строками. Порядок регистрации сохраняется. Пустой массив ничего не добавляет. Класс не проверяет существование файлов: это ответственность проекта и среды развертывания.

Если инициализатор не передан, init() требует доступный CJSCore::init(). При отсутствии метода выбрасывается LogicException.

Передача объекта и callback через конструктор позволяет тестировать регистрацию без ядра Bitrix.

## Миграция со старого lema.lib

Старый API:

    $assets = new \\Lema\\Common\\AssetManager();
    $assets
        ->addCssArray(['/css/site.css'])
        ->init(['fx', 'jquery2'])
        ->addJsArray(['/js/site.js']);

Необязательные адаптеры находятся в legacy/Lema/Common/Asset.php и legacy/Lema/Common/AssetManager.php. Они сохраняют старые имена классов, конструктор без аргументов, методы addCssArray(), addJsArray(), init() и цепочку вызовов.

Подключайте адаптеры явно после Composer autoload и только после отключения исходного модуля lema.lib, чтобы не получить конфликт классов:

    require_once __DIR__ . '/legacy/Lema/Common/Asset.php';
    require_once __DIR__ . '/legacy/Lema/Common/AssetManager.php';

Старый Lema\\Common\\Asset::get() возвращает штатный объект Bitrix\\Main\\Page\\Asset. Новая реализация не наследует старые базовые singleton-классы и не создаёт дополнительное хранилище экземпляра.

## Границы проверки

Автономный smoke-тест проверяет передачу файлов, порядок вызовов, fluent API, пустые списки, валидацию и ошибки неподходящего объекта. Он не доказывает фактический вывод тегов в HTML.

На установленном ядре Bitrix отдельно необходимо проверить:
- регистрацию файлов в контексте страницы;
- фактический вывод CSS/JS в нужных областях шаблона;
- доступность и корректную работу расширений CJSCore;
- поведение на минимальной и целевой версиях ядра проекта.

Запуск автономного теста из каталога local:

    composer test:asset
