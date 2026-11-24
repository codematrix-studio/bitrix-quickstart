<?php

declare(strict_types=1);

namespace Vendor\AdvancedModule\Agent;

/**
 * Безопасный пример фонового агента.
 */
final class ExampleAgent
{
    /**
     * Выполняет один шаг задачи и планирует следующий запуск.
     *
     * @return string Код вызова для следующего запуска; пустая строка останавливает агент.
     */
    public static function run(): string
    {
        // Здесь разместите один небольшой шаг фоновой обработки.

        return __METHOD__ . '();';
    }
}
