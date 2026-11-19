# Контекст Bitrix

Раздел содержит фасады над объектами текущего запроса Bitrix:

- `Context::get()` — возвращает объект контекста из `Bitrix\Main\Application::getInstance()->getContext()`;
- `Request::get()` — возвращает HTTP-запрос из текущего контекста;
- `Page` — определяет принадлежность текущего URL каталогу или корневому разделу;
- `PerClassBitrixInstance` — хранит отдельный экземпляр для каждого класса-наследника.

## Использование

```php
use Project\Bitrix\Context\Page;
use Project\Bitrix\Context\Request;

$request = Request::get();
$currentDirectory = $request->getRequestedPageDirectory();

if (Page::inDirectory(['/catalog/', '/news/'])) {
    // Текущая страница находится в одном из указанных каталогов.
}

if (Page::inRootDirectory('catalog')) {
    // Первый сегмент URL — catalog.
}
```

`Page::inDirectory()` учитывает границы сегментов: `/catalog-old/` не является вложенным адресом `/catalog/`. `Page::inRootDirectory()` проверяет только первый сегмент пути.

## Получение и кеширование

Объекты приложения, контекста и запроса получаются лениво — только при первом обращении. Каждый фасад кеширует свой объект отдельно. При отсутствии ядра Bitrix, контекста или HTTP-запроса выбрасывается `RuntimeException`.

Для обычного HTTP-запроса сбрасывать кеш не требуется. В тестах и долгоживущих процессах (например, workers) после смены контекста используйте:

```php
Request::resetInstance();
Context::resetInstance();
```

Сбрасывать нужно оба фасада, чтобы `Request` не сохранил объект из предыдущего контекста.

## Старый API

Старый `Lema\Common\Page` доступен через необязательный адаптер `legacy/Lema/Common/Page.php`. Он сохраняет методы `inDir()` и `inRootDir()`, но делегирует сопоставление путей новому `Page`.

Старые базовые классы `Lema\Base\BitrixInstances` используют `PerClassBitrixInstance` через адаптер и сохраняют точку расширения `setInstance()`.

## Автономные тесты

Проверки выполняются без установленного ядра Битрикс:

```bash
cd local
composer test:context
```

Тест подменяет минимальный интерфейс `Bitrix\Main\Application` и проверяет ленивое получение, кеширование, сброс и ошибки. Это не заменяет интеграционную проверку на реальном ядре.
