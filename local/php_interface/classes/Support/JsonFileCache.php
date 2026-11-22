<?php

declare(strict_types=1);

namespace Project\Support;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * Безопасный файловый кеш JSON.
 *
 * Запись выполняется во временный файл с последующей атомарной заменой.
 * Чтение и запись одного ключа синхронизируются через flock().
 */
final class JsonFileCache
{
    private string $directory;

    public function __construct(string $directory)
    {
        $directory = rtrim(trim($directory), DIRECTORY_SEPARATOR);

        if ($directory === '') {
            throw new InvalidArgumentException('Не задан каталог файлового кеша.');
        }

        $this->directory = $directory;
        $this->ensureDirectory();
    }

    public function get(string $key): ?array
    {
        $file = $this->filePath($key);
        $lock = $this->lock($key, LOCK_SH);

        try {
            if (!is_file($file) || !is_readable($file)) {
                return null;
            }

            $json = file_get_contents($file);
            if ($json === false || $json === '') {
                return null;
            }

            try {
                $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                return null;
            }

            return is_array($data) ? $data : null;
        } finally {
            $this->unlock($lock);
        }
    }

    public function put(string $key, array $data): void
    {
        $file = $this->filePath($key);
        $lock = $this->lock($key, LOCK_EX);
        $temporaryFile = null;

        try {
            try {
                $json = json_encode(
                    $data,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
            } catch (JsonException $exception) {
                throw new RuntimeException('Не удалось преобразовать данные кеша в JSON.', 0, $exception);
            }

            $temporaryFile = tempnam($this->directory, '.' . $key . '.');
            if ($temporaryFile === false) {
                throw new RuntimeException('Не удалось создать временный файл кеша.');
            }

            $writtenBytes = file_put_contents($temporaryFile, $json, LOCK_EX);
            if ($writtenBytes === false || $writtenBytes !== strlen($json)) {
                throw new RuntimeException('Не удалось полностью записать временный файл кеша.');
            }

            @chmod($temporaryFile, 0640);

            if (!rename($temporaryFile, $file)) {
                throw new RuntimeException('Не удалось атомарно обновить файл кеша.');
            }

            $temporaryFile = null;
        } finally {
            if ($temporaryFile !== null && is_file($temporaryFile)) {
                @unlink($temporaryFile);
            }

            $this->unlock($lock);
        }
    }

    public function isFresh(string $key, int $ttl): bool
    {
        if ($ttl < 0) {
            throw new InvalidArgumentException('Время жизни кеша не может быть отрицательным.');
        }

        $file = $this->filePath($key);
        if (!is_file($file)) {
            return false;
        }

        $modifiedAt = filemtime($file);
        if ($modifiedAt === false) {
            return false;
        }

        $age = time() - $modifiedAt;

        return $age >= 0 && $age <= $ttl;
    }

    public function delete(string $key): bool
    {
        $file = $this->filePath($key);
        $lock = $this->lock($key, LOCK_EX);

        try {
            return !is_file($file) || unlink($file);
        } finally {
            $this->unlock($lock);
        }
    }

    private function filePath(string $key): string
    {
        if ($key === '' || $key === '.' || $key === '..'
            || preg_match('/^[a-zA-Z0-9._-]+$/D', $key) !== 1
        ) {
            throw new InvalidArgumentException('Недопустимое имя файла кеша.');
        }

        return $this->directory . DIRECTORY_SEPARATOR . $key . '.json';
    }

    private function ensureDirectory(): void
    {
        if (is_dir($this->directory)) {
            return;
        }

        if (!@mkdir($this->directory, 0750, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Не удалось создать каталог файлового кеша.');
        }
    }

    /** @return resource */
    private function lock(string $key, int $operation)
    {
        $this->filePath($key);
        $handle = fopen($this->directory . DIRECTORY_SEPARATOR . '.' . $key . '.lock', 'c');

        if ($handle === false || !flock($handle, $operation)) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            throw new RuntimeException('Не удалось заблокировать файл кеша.');
        }

        return $handle;
    }

    /** @param resource $handle */
    private function unlock($handle): void
    {
        if (is_resource($handle)) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
