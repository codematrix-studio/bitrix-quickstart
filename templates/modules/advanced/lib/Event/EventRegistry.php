<?php

declare(strict_types=1);

namespace Vendor\AdvancedModule\Event;

use Bitrix\Main\EventManager;

/**
 * Единый реестр постоянных обработчиков модуля.
 *
 * Параметры регистрации и снятия хранятся рядом, чтобы uninstall
 * использовал те же идентификаторы подписки, что и install.
 */
final class EventRegistry
{
    private const MODULE_ID = 'vendor.advancedmodule';

    /**
     * @return list<array{from: string, event: string, to: string, class: string, method: string, sort: int, path: string, args: array}>
     */
    private static function handlers(): array
    {
        return [
            [
                'from' => 'main',
                'event' => 'OnAfterUserAdd',
                'to' => self::MODULE_ID,
                'class' => ExampleEventHandler::class,
                'method' => 'onAfterUserAdd',
                'sort' => 100,
                'path' => '',
                'args' => [],
            ],
        ];
    }

    /**
     * Регистрирует постоянные обработчики в базе данных Битрикс.
     */
    public static function register(): void
    {
        $eventManager = EventManager::getInstance();

        foreach (self::handlers() as $handler) {
            $eventManager->registerEventHandler(
                $handler['from'],
                $handler['event'],
                $handler['to'],
                $handler['class'],
                $handler['method'],
                $handler['sort'],
                $handler['path'],
                $handler['args']
            );
        }
    }

    /**
     * Снимает постоянные обработчики с теми же идентификаторами.
     */
    public static function unregister(): void
    {
        $eventManager = EventManager::getInstance();

        foreach (self::handlers() as $handler) {
            $eventManager->unRegisterEventHandler(
                $handler['from'],
                $handler['event'],
                $handler['to'],
                $handler['class'],
                $handler['method'],
                $handler['path'],
                $handler['args']
            );
        }
    }
}
