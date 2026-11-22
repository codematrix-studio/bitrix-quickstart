<?php
declare(strict_types=1);

require_once __DIR__ . '/../local/php_interface/classes/Support/Logger.php';

use Project\Support\Logger;

function loggerExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bitrix-quickstart-logger-' . bin2hex(random_bytes(5));

try {
    Logger::configure(false, $directory);
    loggerExpect(!Logger::isEnabled(), 'Логгер должен быть выключен при enabled=false.');
    loggerExpect(!Logger::info('Не записывать'), 'Отключённый логгер должен игнорировать запись.');
    loggerExpect(!is_dir($directory), 'Отключённый логгер не должен создавать каталог.');

    Logger::configure(true, $directory);
    loggerExpect(Logger::info('Тестовая запись', ['order_id' => 42]), 'Запись должна завершиться успешно.');
    $files = glob($directory . DIRECTORY_SEPARATOR . '*.log');
    loggerExpect(is_array($files) && count($files) === 1, 'Должен создаваться один дневной файл.');

    $content = file_get_contents($files[0]);
    loggerExpect(is_string($content) && str_contains($content, '"level":"INFO"'), 'Уровень должен сохраняться.');
    loggerExpect(str_contains($content, '"order_id":42'), 'Контекст должен сохраняться в JSON.');
    loggerExpect(!Logger::log('INVALID', 'Недопустимый уровень'), 'Неизвестный уровень должен отклоняться.');
    loggerExpect(Logger::error('Тестовая ошибка'), 'Метод error() должен записывать событие.');

    $lines = file($files[0], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    loggerExpect(is_array($lines) && count($lines) === 2, 'Каждое событие должно быть отдельной строкой.');

    Logger::configure(false, $directory);
    loggerExpect(!Logger::warning('Не записывать'), 'После выключения запись должна прекращаться.');
    $lines = file($files[0], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    loggerExpect(is_array($lines) && count($lines) === 2, 'После выключения новые строки не должны появляться.');

    echo "Проверки Logger пройдены.\n";
} finally {
    foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    if (is_dir($directory)) {
        rmdir($directory);
    }
}
