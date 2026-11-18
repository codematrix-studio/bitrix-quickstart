<?php

declare(strict_types=1);

namespace Project\Bitrix\File;

use InvalidArgumentException;
use Project\Support\FileTypeIconResolver;
use RuntimeException;

/**
 * Получает MIME-тип файла Bitrix и выбирает CSS-класс иконки.
 */
final class FileIconResolver
{
    /** @var callable(int): array<string, mixed>|false|null */
    private $fileInfoProvider;

    /** @var callable(array<string, mixed>): string|null */
    private $mimeDetector;

    public function __construct(
        ?callable $fileInfoProvider = null,
        private readonly ?FileTypeIconResolver $iconResolver = null,
        ?callable $mimeDetector = null
    ) {
        $this->fileInfoProvider = $fileInfoProvider;
        $this->mimeDetector = $mimeDetector;
    }

    public function iconClassForId(int $fileId): string
    {
        if ($fileId <= 0) {
            throw new InvalidArgumentException('Идентификатор файла должен быть положительным числом.');
        }

        $fileInfo = $this->fileInfo($fileId);
        if (!is_array($fileInfo)) {
            return ($this->iconResolver ?? new FileTypeIconResolver())->iconClassForMimeType(null);
        }

        $mimeType = $this->mimeDetector !== null
            ? ($this->mimeDetector)($fileInfo)
            : $this->detectMimeType($fileInfo);

        if (!is_string($mimeType) || trim($mimeType) === '') {
            $mimeType = $fileInfo['CONTENT_TYPE'] ?? null;
        }

        return ($this->iconResolver ?? new FileTypeIconResolver())
            ->iconClassForMimeType(is_string($mimeType) ? $mimeType : null);
    }

    /**
     * @return array<string, mixed>|false
     */
    private function fileInfo(int $fileId): array|false
    {
        if ($this->fileInfoProvider !== null) {
            $fileInfo = ($this->fileInfoProvider)($fileId);

            return is_array($fileInfo) ? $fileInfo : false;
        }

        if (!class_exists('CFile') || !is_callable(['CFile', 'GetFileArray'])) {
            throw new RuntimeException('Для получения файла необходимо ядро Bitrix с CFile::GetFileArray().');
        }

        $fileInfo = \CFile::GetFileArray($fileId);

        return is_array($fileInfo) ? $fileInfo : false;
    }

    /**
     * Определяет MIME по содержимому локального файла, если он доступен.
     *
     * @param array<string, mixed> $fileInfo
     */
    private function detectMimeType(array $fileInfo): ?string
    {
        if (!class_exists(\finfo::class) || !defined('DOCUMENT_ROOT')) {
            return null;
        }

        $source = $fileInfo['SRC'] ?? null;
        if (!is_string($source) || $source === '' || $source[0] !== '/') {
            return null;
        }

        $documentRoot = realpath((string) DOCUMENT_ROOT);
        $filePath = realpath(rtrim((string) DOCUMENT_ROOT, DIRECTORY_SEPARATOR) . $source);

        if ($documentRoot === false || $filePath === false || !is_file($filePath)) {
            return null;
        }

        $prefix = rtrim($documentRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($filePath, $prefix)) {
            return null;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);

        return is_string($mimeType) ? $mimeType : null;
    }
}
