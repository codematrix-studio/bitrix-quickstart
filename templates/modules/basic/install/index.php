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
 */
class vendor_module extends CModule
{
    /** @var string Уникальный идентификатор модуля. */
    public $MODULE_ID = 'vendor.module';

    /** @var string Версия модуля. */
    public $MODULE_VERSION;

    /** @var string Дата версии модуля. */
    public $MODULE_VERSION_DATE;

    /** @var string Название модуля. */
    public $MODULE_NAME;

    /** @var string Описание модуля. */
    public $MODULE_DESCRIPTION;

    /**
     * Заполняет метаданные установщика.
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
     * Выполняет действия, необходимые перед регистрацией модуля.
     *
     * Добавляйте создание таблиц только при наличии схемы и миграций.
     *
     * @return bool Результат установки структуры данных.
     */
    public function InstallDB()
    {
        return true;
    }

    /**
     * Удаляет структуру данных модуля.
     *
     * Не удаляйте данные без явного решения проекта о политике хранения.
     *
     * @return bool Результат удаления структуры данных.
     */
    public function UnInstallDB()
    {
        return true;
    }

    /**
     * Регистрирует обработчики событий модуля.
     *
     * @return bool Результат регистрации обработчиков.
     */
    public function InstallEvents()
    {
        return true;
    }

    /**
     * Удаляет обработчики событий модуля.
     *
     * @return bool Результат удаления обработчиков.
     */
    public function UnInstallEvents()
    {
        return true;
    }

    /**
     * Устанавливает файлы модуля.
     *
     * По умолчанию файлы сайта не копируются.
     *
     * @return bool Результат установки файлов.
     */
    public function InstallFiles()
    {
        return true;
    }

    /**
     * Удаляет файлы, установленные модулем.
     *
     * Добавляйте удаление только конкретных файлов и каталогов модуля.
     *
     * @return bool Результат удаления файлов.
     */
    public function UnInstallFiles()
    {
        return true;
    }

    /**
     * Запускает установку модуля.
     *
     * @return void
     */
    public function DoInstall()
    {
        if (IsModuleInstalled($this->MODULE_ID)) {
            return;
        }

        if (!$this->InstallDB()) {
            return;
        }

        if (!$this->InstallEvents()) {
            $this->UnInstallDB();

            return;
        }

        if (!$this->InstallFiles()) {
            $this->UnInstallFiles();
            $this->UnInstallEvents();
            $this->UnInstallDB();

            return;
        }

        RegisterModule($this->MODULE_ID);
    }

    /**
     * Запускает удаление модуля.
     *
     * @return void
     */
    public function DoUninstall()
    {
        $this->UnInstallEvents();
        $this->UnInstallFiles();
        $this->UnInstallDB();

        UnRegisterModule($this->MODULE_ID);
    }
}
