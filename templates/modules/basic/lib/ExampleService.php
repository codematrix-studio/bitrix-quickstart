<?php

declare(strict_types=1);

namespace Vendor\Module;

/**
 * Пример сервиса собственного модуля.
 *
 * Замените демонстрационный класс реальной логикой модуля.
 *
 * @package Vendor\Module
 */
final class ExampleService
{
    /**
     * Возвращает признак доступности сервиса.
     *
     * Используйте этот метод как место для простой проверки готовности
     * зависимостей модуля. Не размещайте здесь побочные эффекты.
     *
     * @return bool Признак доступности сервиса.
     */
    public function isAvailable(): bool
    {
        return true;
    }
}
