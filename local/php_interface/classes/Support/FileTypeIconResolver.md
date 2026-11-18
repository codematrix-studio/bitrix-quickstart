# FileTypeIconResolver

`Project\Support\FileTypeIconResolver` — независимое от Bitrix сопоставление MIME-типов с CSS-классами иконок.

```php
use Project\Support\FileTypeIconResolver;

$icons = new FileTypeIconResolver();
$class = $icons->iconClassForMimeType('application/pdf'); // ico_pdf
```

По умолчанию сохранены классы старого помощника:

| MIME | CSS-класс |
| --- | --- |
| `application/pdf` | `ico_pdf` |
| `application/msword` | `ico_doc` |
| `application/vnd.openxmlformats-officedocument.wordprocessingml.document` | `ico_doc` |
| `image/jpeg` | `ico_jpg` |
| `application/zip`, `application/x-zip-compressed` | `ico_zip` |
| Неизвестный или пустой MIME | `ico_cd` |

Таблица расширяется или переопределяется через конструктор:

```php
$icons = new FileTypeIconResolver([
    'image/png' => 'ico_png',
    'text/plain' => 'ico_txt',
], 'ico_file');
```

Параметры `iconClassForMimeType()` и `iconClassForFileInfo()` используют MIME, а не расширение имени. MIME нормализуется: регистр и параметры после `;` не влияют на поиск.

## Интеграция с Bitrix

`Project\Bitrix\File\FileIconResolver` получает файл через `CFile::GetFileArray()` и определяет MIME по содержимому локального файла через PHP Fileinfo, если путь доступен и находится внутри `DOCUMENT_ROOT`. Если файл недоступен для чтения, используется `CONTENT_TYPE` из метаданных Bitrix. Для отсутствующего файла возвращается резервная иконка.

```php
use Project\Bitrix\File\FileIconResolver;

$iconClass = (new FileIconResolver())->iconClassForId($fileId);
```

Для тестирования или нестандартного хранилища можно передать провайдер метаданных и определитель MIME в конструктор. Определитель должен вернуть MIME-строку либо `null`.

## Legacy

Адаптер `legacy/Ns/Bitrix/Helper/Files/Checker.php` сохраняет метод:

```php
$checker->chooseFileIcoClass($fileId);
```

Глобальные константы `PDF`, `JPG`, `DOC` и `ZIP` больше не объявляются: они создавали побочные эффекты и конфликты имён. Старый `HelperCore` не наследуется — его магическое хранилище состояния здесь не использовалось.

## Ограничения

Если файл лежит во внешнем хранилище или недоступен локально, достоверное определение по содержимому невозможно без отдельного адаптера хранилища. В этом случае используется MIME из метаданных Bitrix. Неизвестные MIME не угадываются по расширению и получают резервный CSS-класс.

Автономный smoke test проверяет таблицу, нормализацию MIME, переопределения, приоритет определения по содержимому, fallback и legacy API. Получение файлов реальным ядром Bitrix требует интеграционной проверки.
