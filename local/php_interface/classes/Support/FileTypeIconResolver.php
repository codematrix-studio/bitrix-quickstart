<?php

declare(strict_types=1);

namespace Project\Support;

use InvalidArgumentException;

/**
 * Сопоставляет MIME-типы файлов с CSS-классами иконок.
 *
 * Класс не зависит от Bitrix и не определяет MIME по расширению файла.
 */
final class FileTypeIconResolver
{
    private const DEFAULT_MAP = [
        'application/pdf' => 'ico_pdf',
        'application/msword' => 'ico_doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'ico_doc',
        'image/jpeg' => 'ico_jpg',
        'image/pjpeg' => 'ico_jpg',
        'application/zip' => 'ico_zip',
        'application/x-zip-compressed' => 'ico_zip',
    ];

    /** @var array<string, string> */
    private array $map;

    /**
     * @param array<string, string> $map Дополнительные или переопределяющие MIME-сопоставления.
     */
    public function __construct(array $map = [], private readonly string $fallback = 'ico_cd')
    {
        if (trim($this->fallback) === '') {
            throw new InvalidArgumentException('Класс иконки по умолчанию не может быть пустым.');
        }

        $normalized = [];
        foreach (array_replace(self::DEFAULT_MAP, $map) as $mimeType => $iconClass) {
            if (!is_string($mimeType) || !str_contains($mimeType, '/')) {
                throw new InvalidArgumentException('Ключ таблицы должен быть MIME-типом.');
            }
            if (!is_string($iconClass) || trim($iconClass) === '') {
                throw new InvalidArgumentException('CSS-класс иконки должен быть непустой строкой.');
            }

            $normalized[self::normalizeMimeType($mimeType)] = $iconClass;
        }

        $this->map = $normalized;
    }

    public function iconClassForMimeType(?string $mimeType): string
    {
        if ($mimeType === null || trim($mimeType) === '') {
            return $this->fallback;
        }

        return $this->map[self::normalizeMimeType($mimeType)] ?? $this->fallback;
    }

    /**
     * @param array<string, mixed> $fileInfo Массив метаданных файла, например CFile::GetFileArray().
     */
    public function iconClassForFileInfo(array $fileInfo): string
    {
        $mimeType = $fileInfo['CONTENT_TYPE'] ?? null;

        return $this->iconClassForMimeType(is_string($mimeType) ? $mimeType : null);
    }

    private static function normalizeMimeType(string $mimeType): string
    {
        $mimeType = strtolower(trim($mimeType));
        $separator = strpos($mimeType, ';');

        if ($separator !== false) {
            $mimeType = trim(substr($mimeType, 0, $separator));
        }

        return $mimeType;
    }
}
