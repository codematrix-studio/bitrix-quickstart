<?php
declare(strict_types=1);

namespace Project\Support;

/** Общий файловый логгер проекта с форматом хранения JSON Lines. */
final class Logger
{
    private const LEVELS = ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];
    private static bool $enabled = false;
    private static string $directory = '';

    /** @param bool $enabled Разрешена ли запись. @param string $directory Абсолютный путь к каталогу логов. */
    public static function configure(bool $enabled, string $directory): void
    {
        self::$enabled = $enabled;
        self::$directory = rtrim(trim($directory), DIRECTORY_SEPARATOR);
    }

    public static function isEnabled(): bool
    {
        return self::$enabled && self::$directory !== '';
    }

    /** @param array<string, mixed> $context Диагностический контекст без секретов и лишних персональных данных. */
    public static function log(string $level, string $message, array $context = []): bool
    {
        if (!self::isEnabled()) {
            return false;
        }

        $level = strtoupper(trim($level));
        if (!in_array($level, self::LEVELS, true)) {
            return false;
        }

        try {
            if (!is_dir(self::$directory)
                && !@mkdir(self::$directory, 0750, true)
                && !is_dir(self::$directory)
            ) {
                return false;
            }

            $json = json_encode([
                'timestamp' => date(DATE_ATOM),
                'level' => $level,
                'message' => $message,
                'context' => $context,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $file = self::$directory . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';
            // Предупреждения файловой системы не должны загрязнять HTTP-ответ при сбое логгера.
            $written = @file_put_contents($file, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
            if ($written === false) {
                return false;
            }

            @chmod($file, 0640);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function debug(string $message, array $context = []): bool { return self::log('DEBUG', $message, $context); }
    public static function info(string $message, array $context = []): bool { return self::log('INFO', $message, $context); }
    public static function notice(string $message, array $context = []): bool { return self::log('NOTICE', $message, $context); }
    public static function warning(string $message, array $context = []): bool { return self::log('WARNING', $message, $context); }
    public static function error(string $message, array $context = []): bool { return self::log('ERROR', $message, $context); }
    public static function critical(string $message, array $context = []): bool { return self::log('CRITICAL', $message, $context); }
    public static function alert(string $message, array $context = []): bool { return self::log('ALERT', $message, $context); }
    public static function emergency(string $message, array $context = []): bool { return self::log('EMERGENCY', $message, $context); }
}
