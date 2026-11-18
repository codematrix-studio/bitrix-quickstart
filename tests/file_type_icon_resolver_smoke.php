<?php

declare(strict_types=1);

require_once __DIR__ . '/../local/php_interface/classes/Support/FileTypeIconResolver.php';
require_once __DIR__ . '/../local/php_interface/classes/Bitrix/File/FileIconResolver.php';
require_once __DIR__ . '/../local/php_interface/legacy/Ns/Bitrix/Helper/Files/Checker.php';

use Ns\Bitrix\Helper\Files\Checker;
use Project\Bitrix\File\FileIconResolver;
use Project\Support\FileTypeIconResolver;

function fileIconExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$resolver = new FileTypeIconResolver();

fileIconExpect($resolver->iconClassForMimeType('application/pdf') === 'ico_pdf', 'PDF должен получать прежний класс иконки.');
fileIconExpect($resolver->iconClassForMimeType('application/msword') === 'ico_doc', 'DOC должен получать класс документа.');
fileIconExpect($resolver->iconClassForMimeType('application/vnd.openxmlformats-officedocument.wordprocessingml.document') === 'ico_doc', 'DOCX должен получать класс документа.');
fileIconExpect($resolver->iconClassForMimeType('image/jpeg') === 'ico_jpg', 'JPEG должен получать прежний класс изображения.');
fileIconExpect($resolver->iconClassForMimeType('application/zip') === 'ico_zip', 'ZIP должен получать прежний класс архива.');
fileIconExpect($resolver->iconClassForMimeType('application/x-zip-compressed') === 'ico_zip', 'Альтернативный MIME ZIP должен поддерживаться.');
fileIconExpect($resolver->iconClassForMimeType('image/png') === 'ico_cd', 'Неизвестный тип должен получать резервный класс.');
fileIconExpect($resolver->iconClassForMimeType(null) === 'ico_cd', 'Отсутствующий MIME должен получать резервный класс.');
fileIconExpect($resolver->iconClassForMimeType(' Application/PDF; charset=binary ') === 'ico_pdf', 'MIME должен нормализоваться.');

$custom = new FileTypeIconResolver(['image/png' => 'ico_png'], 'ico_file');
fileIconExpect($custom->iconClassForMimeType('image/png') === 'ico_png', 'Пользовательская таблица должна расширять сопоставления.');
fileIconExpect($custom->iconClassForMimeType('text/plain') === 'ico_file', 'Пользовательский резервный класс должен применяться.');

$invalidMapFailed = false;
try {
    new FileTypeIconResolver(['not-a-mime' => 'ico_invalid']);
} catch (InvalidArgumentException) {
    $invalidMapFailed = true;
}
fileIconExpect($invalidMapFailed, 'Некорректные ключи таблицы должны отклоняться.');

$lookedUpId = null;
$bitrixResolver = new FileIconResolver(
    static function (int $id) use (&$lookedUpId): array|false {
        $lookedUpId = $id;

        return ['CONTENT_TYPE' => 'application/octet-stream', 'FILE_NAME' => 'manual.pdf'];
    },
    $resolver,
    static fn (array $fileInfo): string => 'application/pdf'
);
fileIconExpect($bitrixResolver->iconClassForId(42) === 'ico_pdf', 'Результат определения MIME по содержимому должен иметь приоритет над метаданными.');
fileIconExpect($lookedUpId === 42, 'Провайдер должен получить ID файла.');

$metadataFallback = new FileIconResolver(
    static fn (int $id): array => ['CONTENT_TYPE' => 'image/jpeg'],
    $resolver,
    static fn (array $fileInfo): ?string => null
);
fileIconExpect($metadataFallback->iconClassForId(7) === 'ico_jpg', 'При недоступности определения по содержимому используется MIME из метаданных Bitrix.');

$missingFile = new FileIconResolver(static fn (int $id) => false);
fileIconExpect($missingFile->iconClassForId(999) === 'ico_cd', 'Для отсутствующего файла должен возвращаться резервный класс.');

$invalidIdFailed = false;
try {
    $bitrixResolver->iconClassForId(0);
} catch (InvalidArgumentException) {
    $invalidIdFailed = true;
}
fileIconExpect($invalidIdFailed, 'Нулевой ID файла должен отклоняться.');

$legacy = new Checker(new FileIconResolver(
    static fn (int $id): array => ['CONTENT_TYPE' => 'application/pdf'],
    $resolver,
    static fn (array $fileInfo): ?string => null
));
fileIconExpect($legacy->chooseFileIcoClass(5) === 'ico_pdf', 'Legacy-адаптер должен сохранять chooseFileIcoClass().');

echo "Проверки FileTypeIconResolver пройдены.\n";
