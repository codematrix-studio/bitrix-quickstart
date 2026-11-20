<?php

use Bitrix\Main\Localization\Loc;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

IncludeModuleLangFile(__FILE__);

/**
 * Установщик базового модуля Битрикс.
 *
 * Замените идентификатор модуля и имя класса перед использованием.
 * Методы установки ресурсов должны быть идемпотентными: при повторном вызове
 * они не должны создавать дубли и должны уметь завершать откат после сбоя.
 */
class vendor_module extends CModule
{
    /** @var string Уникальный идентификатор модуля в формате vendor.module. */
    public $MODULE_ID = 'vendor.module';

    /** @var string Версия модуля в формате, принятом проектом. */
    public $MODULE_VERSION;

    /** @var string Дата версии модуля. */
    public $MODULE_VERSION_DATE;

    /** @var string Название модуля для административного интерфейса. */
    public $MODULE_NAME;

    /** @var string Краткое описание модуля для административного интерфейса. */
    public $MODULE_DESCRIPTION;

    /**
     * Заполняет метаданные установщика из файла версии и языкового файла.
     *
     * @return void
     */
    public function __construct()
    {
        $arModuleVersion = [];

        include __DIR__ . '/version.php';

        $this->MODULE_VERSION = (string) ($arModuleVersion['VERSION'] ?? '1.0.0');
        $this->MODULE_VERSION_DATE = (string) ($arModuleVersion['VERSION_DATE'] ?? '');
        $this->MODULE_NAME = Loc::getMessage('VENDOR_MODULE_NAME') ?: 'Модуль проекта';
        $this->MODULE_DESCRIPTION = Loc::getMessage('VENDOR_MODULE_DESCRIPTION') ?: 'Описание модуля проекта';
    }

    /**
     * Создаёт структуру данных модуля.
     *
     * Реализация должна быть повторяемой и самостоятельно откатывать
     * частично выполненные операции либо корректно обрабатываться UnInstallDB().
     *
     * @return bool Успешность установки структуры данных.
     */
    public function InstallDB()
    {
        return true;
    }

    /**
     * Удаляет только структуру данных, принадлежащую этому модулю.
     *
     * @return bool Успешность удаления структуры данных.
     */
    public function UnInstallDB()
    {
        return true;
    }

    /**
     * Регистрирует обработчики событий модуля.
     *
     * @return bool Успешность регистрации обработчиков.
     */
    public function InstallEvents()
    {
        return true;
    }

    /**
     * Снимает регистрацию обработчиков событий модуля.
     *
     * @return bool Успешность удаления обработчиков.
     */
    public function UnInstallEvents()
    {
        return true;
    }

    /**
     * Устанавливает файлы, принадлежащие модулю.
     *
     * По умолчанию файлы сайта не копируются.
     *
     * @return bool Успешность установки файлов.
     */
    public function InstallFiles()
    {
        return true;
    }

    /**
     * Удаляет только файлы, установленные этим модулем.
     *
     * @return bool Успешность удаления файлов.
     */
    public function UnInstallFiles()
    {
        return true;
    }

    /**
     * Устанавливает модуль с откатом уже начатых этапов при ошибке.
     *
     * @return void
     */
    public function DoInstall(): void
    {
        if (IsModuleInstalled($this->MODULE_ID)) {
            return;
        }

        $attemptedSteps = [];

        try {
            $attemptedSteps[] = 'database';
            if (!$this->InstallDB()) {
                throw new \RuntimeException('Не удалось установить структуру данных модуля.');
            }

            $attemptedSteps[] = 'events';
            if (!$this->InstallEvents()) {
                throw new \RuntimeException('Не удалось зарегистрировать обработчики событий модуля.');
            }

            $attemptedSteps[] = 'files';
            if (!$this->InstallFiles()) {
                throw new \RuntimeException('Не удалось установить файлы модуля.');
            }

            RegisterModule($this->MODULE_ID);
        } catch (\Throwable $exception) {
            $this->rollbackInstallation($attemptedSteps);

            error_log(sprintf(
                'Установка модуля %s прервана (%s).',
                $this->MODULE_ID,
                get_class($exception)
            ));
        }
    }

    /**
     * Удаляет ресурсы модуля и снимает регистрацию только после успешных этапов.
     *
     * При ошибке модуль остаётся зарегистрированным, чтобы администратор мог
     * устранить причину и повторить удаление. Методы удаления должны быть идемпотентными.
     *
     * @return void
     */
    public function DoUninstall(): void
    {
        try {
            if (!$this->UnInstallFiles()) {
                return;
            }

            if (!$this->UnInstallEvents()) {
                return;
            }

            if (!$this->UnInstallDB()) {
                return;
            }

            UnRegisterModule($this->MODULE_ID);
        } catch (\Throwable $exception) {
            error_log(sprintf(
                'Удаление модуля %s прервано (%s).',
                $this->MODULE_ID,
                get_class($exception)
            ));
        }
    }

    /**
     * Откатывает все этапы установки, которые уже были начаты.
     *
     * Этап включается в список до вызова установщика, чтобы откатить и частично
     * выполненную операцию, вернувшую false или выбросившую исключение.
     *
     * @param list<string> $attemptedSteps Начатые этапы в порядке выполнения.
     *
     * @return void
     */
    private function rollbackInstallation(array $attemptedSteps): void
    {
        $rollbackMethods = [
            'database' => 'UnInstallDB',
            'events' => 'UnInstallEvents',
            'files' => 'UnInstallFiles',
        ];

        foreach (array_reverse($attemptedSteps) as $step) {
            $method = $rollbackMethods[$step] ?? null;

            if ($method === null) {
                continue;
            }

            try {
                if (!$this->{$method}()) {
                    error_log(sprintf(
                        'Не удалось выполнить этап отката %s для модуля %s.',
                        $method,
                        $this->MODULE_ID
                    ));
                }
            } catch (\Throwable $exception) {
                error_log(sprintf(
                    'Ошибка этапа отката %s для модуля %s (%s).',
                    $method,
                    $this->MODULE_ID,
                    get_class($exception)
                ));
            }
        }
    }
}
