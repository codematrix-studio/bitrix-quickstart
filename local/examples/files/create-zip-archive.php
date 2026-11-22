<?php

/**
 * Создаёт ZIP-архив из файла или каталога.
 *
 * Важно: вызывающий код должен ограничить допустимые исходные пути.
 */
function createZipArchive(string $source, string $destination): bool
{
    if (!class_exists(\ZipArchive::class) || !file_exists($source)) {
        return false;
    }

    $sourceRealPath = realpath($source);
    if ($sourceRealPath === false) {
        return false;
    }

    $destinationDirectory = realpath(dirname($destination));
    if ($destinationDirectory === false || !is_writable($destinationDirectory)) {
        return false;
    }

    $destinationPath = $destinationDirectory . DIRECTORY_SEPARATOR . basename($destination);
    $zip = new \ZipArchive();

    if ($zip->open($destinationPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
        return false;
    }

    $sourceRealPath = rtrim($sourceRealPath, DIRECTORY_SEPARATOR);
    $sourceBaseName = basename($sourceRealPath);

    try {
        if (is_file($sourceRealPath)) {
            $zip->addFile($sourceRealPath, basename($sourceRealPath));
        } else {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $sourceRealPath,
                    \FilesystemIterator::SKIP_DOTS
                ),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->isLink()) {
                    continue;
                }

                $relativePath = substr($file->getPathname(), strlen($sourceRealPath) + 1);
                $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

                if ($relativePath !== '') {
                    $zip->addFile($file->getPathname(), $sourceBaseName . '/' . $relativePath);
                }
            }
        }
    } finally {
        $closed = $zip->close();
    }

    return $closed;
}
