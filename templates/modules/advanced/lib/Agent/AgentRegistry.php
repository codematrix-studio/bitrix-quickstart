<?php

declare(strict_types=1);

namespace Vendor\AdvancedModule\Agent;

/**
 * Реестр системных агентов модуля.
 */
final class AgentRegistry
{
    private const MODULE_ID = 'vendor.advancedmodule';
    private const INTERVAL = 3600;

    /**
     * Возвращает точный код вызова агента без ведущего обратного слеша.
     */
    private static function agentName(): string
    {
        return ExampleAgent::class . '::run();';
    }

    /**
     * Регистрирует агента, не создавая дубликат при повторном вызове.
     *
     * @throws \RuntimeException Если Bitrix не смог зарегистрировать агента.
     */
    public static function register(): void
    {
        $existingAgent = \CAgent::GetList(
            ['ID' => 'ASC'],
            [
                '=NAME' => self::agentName(),
                'MODULE_ID' => self::MODULE_ID,
                'USER_ID' => false,
            ]
        )->Fetch();

        if ($existingAgent) {
            return;
        }

        $agentId = \CAgent::AddAgent(
            self::agentName(),
            self::MODULE_ID,
            'N',
            self::INTERVAL,
            '',
            'Y',
            '',
            100,
            false,
            false
        );

        if ($agentId === false) {
            global $APPLICATION;

            $exception = is_object($APPLICATION) && method_exists($APPLICATION, 'GetException')
                ? $APPLICATION->GetException()
                : null;
            $message = is_object($exception) && method_exists($exception, 'GetString')
                ? $exception->GetString()
                : 'Bitrix не смог зарегистрировать фоновый агент.';

            throw new \RuntimeException($message);
        }
    }

    /**
     * Удаляет только системный агент с точным именем и ID этого модуля.
     */
    public static function unregister(): void
    {
        \CAgent::RemoveAgent(self::agentName(), self::MODULE_ID, false);
    }
}
