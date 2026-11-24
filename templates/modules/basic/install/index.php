<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Config\Option;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

IncludeModuleLangFile(__FILE__);

/**
 * Установщик базового модуля Битрикс.
 *
 * Замените идентификатор модуля и имя класса перед использованием.
 * Методы установки ресурсов должны быть идемпотентными: повторный вызов
 * должен безопасно завершать ранее прерванную установку без создания дублей.
 */
class vendor_module extends CModule
{
    /** @var string Имя настройки, отмечающей незавершённую установку. */
    private const INSTALLATION_INCOMPLETE_OPTION = 'INSTALLATION_INCOMPLETE';
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
     * Модуль регистрируется до установки ресурсов: код InstallDB() и других
     * этапов может использовать Loader::includeModule() для этого модуля.
     *
     * @return bool Успешность установки. При ошибке возвращает false
     *              и передаёт сообщение в административный интерфейс.
     */
    public function DoInstall(): bool
    {
        global $APPLICATION;

        if (!$this->checkAdminAccess()) {
            return false;
        }

        $attemptedSteps = [];
        try {
            $moduleIsInstalled = IsModuleInstalled($this->MODULE_ID);
            $installationIsIncomplete = Option::get(
                $this->MODULE_ID,
                self::INSTALLATION_INCOMPLETE_OPTION,
                'N'
            ) === 'Y';

            // Исправную установку не трогаем. Повторный запуск этапов нужен
            // только для восстановления, явно отмеченного предыдущим запуском.
            if ($moduleIsInstalled && !$installationIsIncomplete) {
                return true;
            }

            if (!$moduleIsInstalled) {
                RegisterModule($this->MODULE_ID);
            }

            Option::set($this->MODULE_ID, self::INSTALLATION_INCOMPLETE_OPTION, 'Y');

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

            // Ошибка удаления служебной отметки не должна запускать откат
            // уже успешно установленных ресурсов.
            try {
                Option::delete($this->MODULE_ID, [
                    'name' => self::INSTALLATION_INCOMPLETE_OPTION,
                ]);
            } catch (\Throwable $optionException) {
                error_log(sprintf(
                    'Не удалось удалить отметку незавершённой установки модуля %s (%s).',
                    $this->MODULE_ID,
                    get_class($optionException)
                ));
            }

            return true;
        } catch (\Throwable $exception) {
            $rollbackSuccessful = $this->rollbackInstallation($attemptedSteps);

            $registrationRemoved = !IsModuleInstalled($this->MODULE_ID);

            if (!$registrationRemoved && $rollbackSuccessful) {
                try {
                    UnRegisterModule($this->MODULE_ID);
                    $registrationRemoved = !IsModuleInstalled($this->MODULE_ID);
                } catch (\Throwable $unregisterException) {
                    error_log(sprintf(
                        'Не удалось снять регистрацию модуля %s после отката (%s).',
                        $this->MODULE_ID,
                        get_class($unregisterException)
                    ));
                }
            }

            if ($registrationRemoved) {
                try {
                    Option::delete($this->MODULE_ID, [
                        'name' => self::INSTALLATION_INCOMPLETE_OPTION,
                    ]);
                } catch (\Throwable $optionException) {
                    error_log(sprintf(
                        'Не удалось удалить служебную отметку модуля %s после отката (%s).',
                        $this->MODULE_ID,
                        get_class($optionException)
                    ));
                }
            }

            $message = sprintf(
                'Установка модуля %s прервана: %s',
                $this->MODULE_ID,
                $exception->getMessage()
            );

            if (!$rollbackSuccessful || !$registrationRemoved) {
                $message .= ' Откат выполнен не полностью; модуль оставлен зарегистрированным для повторного восстановления или удаления.';
            }

            if (is_object($APPLICATION) && method_exists($APPLICATION, 'ThrowException')) {
                $APPLICATION->ThrowException($message);
            }

            error_log($message);

            return false;
        }
    }

    /**
     * Удаляет ресурсы модуля и снимает регистрацию только после успешных этапов.
     *
     * При ошибке модуль остаётся зарегистрированным, чтобы администратор мог
     * устранить причину и повторить удаление. Методы удаления должны быть идемпотентными.
     *
     * @return bool Успешность удаления. При ошибке возвращает false
     *              и передаёт сообщение в административный интерфейс.
     */
    public function DoUninstall(): bool
    {
        global $APPLICATION;

        if (!$this->checkAdminAccess()) {
            return false;
        }

        try {
            if (!$this->UnInstallFiles()) {
                throw new \RuntimeException('Не удалось удалить файлы модуля.');
            }

            if (!$this->UnInstallEvents()) {
                throw new \RuntimeException('Не удалось удалить обработчики событий модуля.');
            }

            if (!$this->UnInstallDB()) {
                throw new \RuntimeException('Не удалось удалить структуру данных модуля.');
            }

            Option::delete($this->MODULE_ID, [
                'name' => self::INSTALLATION_INCOMPLETE_OPTION,
            ]);
            UnRegisterModule($this->MODULE_ID);

            return true;
        } catch (\Throwable $exception) {
            $message = sprintf(
                'Удаление модуля %s прервано: %s',
                $this->MODULE_ID,
                $exception->getMessage()
            );

            if (is_object($APPLICATION) && method_exists($APPLICATION, 'ThrowException')) {
                $APPLICATION->ThrowException($message);
            }

            error_log($message);

            return false;
        }
    }

    /**
     * Проверяет, что установку или удаление запускает администратор.
     *
     * @return bool Разрешён ли доступ к операциям жизненного цикла модуля.
     */
    private function checkAdminAccess(): bool
    {
        global $USER, $APPLICATION;

        if (is_object($USER) && method_exists($USER, 'IsAdmin') && $USER->IsAdmin()) {
            return true;
        }

        $message = Loc::getMessage('VENDOR_MODULE_ACCESS_DENIED')
            ?: 'Устанавливать и удалять модуль может только администратор.';

        if (is_object($APPLICATION) && method_exists($APPLICATION, 'ThrowException')) {
            $APPLICATION->ThrowException($message);
        }

        return false;
    }

    /**
     * Откатывает все этапы установки, которые уже были начаты.
     *
     * Этап включается в список до вызова установщика, чтобы откатить и частично
     * выполненную операцию, вернувшую false или выбросившую исключение.
     *
     * @param list<string> $attemptedSteps Начатые этапы в порядке выполнения.
     *
     * @return bool Успешность отката всех начатых этапов.
     */
    private function rollbackInstallation(array $attemptedSteps): bool
    {
        $rollbackSuccessful = true;
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
                    $rollbackSuccessful = false;

                    error_log(sprintf(
                        'Не удалось выполнить этап отката %s для модуля %s.',
                        $method,
                        $this->MODULE_ID
                    ));
                }
            } catch (\Throwable $exception) {
                $rollbackSuccessful = false;

                error_log(sprintf(
                    'Ошибка этапа отката %s для модуля %s (%s).',
                    $method,
                    $this->MODULE_ID,
                    get_class($exception)
                ));
            }
        }

        return $rollbackSuccessful;
    }
}
