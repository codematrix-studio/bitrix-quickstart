<?php

declare(strict_types=1);

namespace Bitrix\Main\Localization {
    final class Loc
    {
        public static function getMessage(string $code): string
        {
            return $code;
        }
    }
}

namespace Bitrix\Main\Config {
    final class Option
    {
        /** @var array<string, array<string, string>> */
        private static array $options = [];

        public static bool $failNextSet = false;

        public static function get(string $moduleId, string $name, string $default = ''): string
        {
            return self::$options[$moduleId][$name] ?? $default;
        }

        public static function set(string $moduleId, string $name, string $value): void
        {
            if (self::$failNextSet) {
                self::$failNextSet = false;
                throw new \RuntimeException('Ошибка записи настройки модуля.');
            }

            self::$options[$moduleId][$name] = $value;
        }

        /** @param array{name: string} $filter */
        public static function delete(string $moduleId, array $filter = []): void
        {
            $name = $filter['name'] ?? null;
            if ($name !== null) {
                unset(self::$options[$moduleId][$name]);
            }
        }
    }
}

namespace {
    define('B_PROLOG_INCLUDED', true);

    /** @var array<string, bool> $installedModules */
    $installedModules = [];

    /** @var list<string> $lifecycleLog */
    $lifecycleLog = [];

    class CModule
    {
    }

    final class TestUser
    {
        public bool $isAdmin = true;

        public function IsAdmin(): bool
        {
            return $this->isAdmin;
        }
    }

    final class TestApplication
    {
        public ?string $exceptionMessage = null;

        public function ThrowException(string $message): void
        {
            $this->exceptionMessage = $message;
        }
    }

    function IncludeModuleLangFile(string $file): void
    {
    }

    function IsModuleInstalled(string $moduleId): bool
    {
        global $installedModules;

        return $installedModules[$moduleId] ?? false;
    }

    function RegisterModule(string $moduleId): void
    {
        global $installedModules;

        $installedModules[$moduleId] = true;
    }

    function UnRegisterModule(string $moduleId): void
    {
        global $installedModules;

        unset($installedModules[$moduleId]);
    }

    require_once __DIR__ . '/../templates/modules/basic/install/index.php';

    /**
     * Проверяет условие и завершает тест при его нарушении.
     *
     * @param bool $condition Проверяемое условие.
     * @param string $message Сообщение об ошибке.
     *
     * @return void
     */
    $assert = static function (bool $condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    // Метаданные версии должны соответствовать формату установщика Bitrix.
    $arModuleVersion = [];
    require __DIR__ . '/../templates/modules/basic/install/version.php';
    $assert(
        preg_match('/^\\d+\\.\\d+\\.\\d+$/', (string) ($arModuleVersion['VERSION'] ?? '')) === 1,
        'Версия модуля должна иметь формат X.Y.Z.'
    );
    $assert(
        preg_match('/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$/', (string) ($arModuleVersion['VERSION_DATE'] ?? '')) === 1,
        'Дата версии модуля должна иметь формат YYYY-MM-DD HH:MM:SS.'
    );

    /**
     * Проверяет успешную установку и обратный порядок отката.
     */
    class SuccessfulModuleInstaller extends vendor_module
    {
        public function InstallDB()
        {
            $GLOBALS['lifecycleLog'][] = 'install-db';

            return true;
        }

        public function InstallEvents()
        {
            $GLOBALS['lifecycleLog'][] = 'install-events';

            return true;
        }

        public function InstallFiles()
        {
            $GLOBALS['lifecycleLog'][] = 'install-files';

            return true;
        }

        public function UnInstallFiles()
        {
            $GLOBALS['lifecycleLog'][] = 'uninstall-files';

            return true;
        }

        public function UnInstallEvents()
        {
            $GLOBALS['lifecycleLog'][] = 'uninstall-events';

            return true;
        }

        public function UnInstallDB()
        {
            $GLOBALS['lifecycleLog'][] = 'uninstall-db';

            return true;
        }
    }

    /**
     * Имитирует ошибку этапа регистрации событий.
     */
    class FailingEventsInstaller extends SuccessfulModuleInstaller
    {
        public function InstallEvents()
        {
            $GLOBALS['lifecycleLog'][] = 'install-events';

            return false;
        }
    }

    /**
     * Имитирует ситуацию, когда откат событий завершился ошибкой.
     */
    class FailingRollbackInstaller extends FailingEventsInstaller
    {
        public function UnInstallEvents()
        {
            $GLOBALS['lifecycleLog'][] = 'uninstall-events';

            return false;
        }
    }

    /**
     * Имитирует исключение во время регистрации обработчиков.
     */
    final class ThrowingEventsInstaller extends SuccessfulModuleInstaller
    {
        public function InstallEvents()
        {
            $GLOBALS['lifecycleLog'][] = 'install-events';

            throw new RuntimeException('Ошибка регистрации событий.');
        }
    }

    /**
     * Имитирует исключение во время отката обработчиков.
     */
    final class ThrowingRollbackInstaller extends FailingEventsInstaller
    {
        public function UnInstallEvents()
        {
            $GLOBALS['lifecycleLog'][] = 'uninstall-events';

            throw new RuntimeException('Ошибка отката событий.');
        }
    }

    /**
     * Имитирует исключение при удалении файлов.
     */
    final class ThrowingUninstallInstaller extends SuccessfulModuleInstaller
    {
        public function UnInstallFiles()
        {
            $GLOBALS['lifecycleLog'][] = 'uninstall-files';

            throw new RuntimeException('Ошибка удаления файлов.');
        }
    }

    /**
     * Имитирует успешное завершение установки после неудачного отката.
     */
    final class RecoveringInstaller extends FailingRollbackInstaller
    {
        public function InstallEvents()
        {
            $GLOBALS['lifecycleLog'][] = 'install-events';

            return true;
        }
    }

    /**
     * Имитирует ошибку удаления файлов.
     */
    final class FailingUninstallInstaller extends SuccessfulModuleInstaller
    {
        public function UnInstallFiles()
        {
            $GLOBALS['lifecycleLog'][] = 'uninstall-files';

            return false;
        }
    }

    $GLOBALS['APPLICATION'] = new TestApplication();
    $GLOBALS['USER'] = new TestUser();

    // Неадминистратор не должен запускать установку или удаление.
    $GLOBALS['USER']->isAdmin = false;
    $GLOBALS['installedModules'] = [];
    $GLOBALS['lifecycleLog'] = [];
    $GLOBALS['APPLICATION']->exceptionMessage = null;
    $installer = new SuccessfulModuleInstaller();
    $assert(!$installer->DoInstall(), 'Неадминистратор не должен устанавливать модуль.');
    $assert(!IsModuleInstalled('vendor.module'), 'Запрет установки не должен регистрировать модуль.');
    $assert($GLOBALS['lifecycleLog'] === [], 'Запрет установки не должен запускать этапы жизненного цикла.');
    $assert(
        $GLOBALS['APPLICATION']->exceptionMessage === 'VENDOR_MODULE_ACCESS_DENIED',
        'При запрете установки сообщение должно передаваться в административный интерфейс.'
    );

    $GLOBALS['installedModules'] = ['vendor.module' => true];
    $GLOBALS['lifecycleLog'] = [];
    $GLOBALS['APPLICATION']->exceptionMessage = null;
    $assert(!$installer->DoUninstall(), 'Неадминистратор не должен удалять модуль.');
    $assert(IsModuleInstalled('vendor.module'), 'Запрет удаления не должен снимать регистрацию модуля.');
    $assert($GLOBALS['lifecycleLog'] === [], 'Запрет удаления не должен запускать этапы жизненного цикла.');
    $assert(
        $GLOBALS['APPLICATION']->exceptionMessage === 'VENDOR_MODULE_ACCESS_DENIED',
        'При запрете удаления сообщение должно передаваться в административный интерфейс.'
    );
    $GLOBALS['USER']->isAdmin = true;

    // Успешная установка должна выполнить этапы в прямом порядке.
    $GLOBALS['installedModules'] = [];
    $GLOBALS['lifecycleLog'] = [];
    $installer = new SuccessfulModuleInstaller();
    $assert($installer->DoInstall(), 'Успешная установка должна вернуть true.');
    $assert(IsModuleInstalled('vendor.module'), 'После установки модуль должен быть зарегистрирован.');
    $assert(
        \Bitrix\Main\Config\Option::get('vendor.module', 'INSTALLATION_INCOMPLETE', 'N') !== 'Y',
        'После успешной установки не должна оставаться отметка незавершённой установки.'
    );
    $assert(
        $GLOBALS['lifecycleLog'] === ['install-db', 'install-events', 'install-files'],
        'Этапы установки выполнены в неверном порядке.'
    );

    // Повторный вызов исправной установки не должен повторно запускать этапы.
    $GLOBALS['lifecycleLog'] = [];
    $assert($installer->DoInstall(), 'Повторный вызов исправной установки должен быть безопасным.');
    $assert($GLOBALS['lifecycleLog'] === [], 'Для исправно установленного модуля этапы не должны запускаться повторно.');

    // При ошибке события и база должны откатиться в обратном порядке.
    $GLOBALS['installedModules'] = [];
    $GLOBALS['lifecycleLog'] = [];
    $installer = new FailingEventsInstaller();
    $assert(!$installer->DoInstall(), 'Ошибка этапа установки должна вернуть false.');
    $assert(!IsModuleInstalled('vendor.module'), 'После успешного отката регистрация должна быть снята.');
    $assert(
        \Bitrix\Main\Config\Option::get('vendor.module', 'INSTALLATION_INCOMPLETE', 'N') !== 'Y',
        'После успешного отката не должна оставаться отметка незавершённой установки.'
    );
    $assert(
        $GLOBALS['lifecycleLog'] === [
            'install-db',
            'install-events',
            'uninstall-events',
            'uninstall-db',
        ],
        'Откат должен включать неуспешный этап и выполняться в обратном порядке.'
    );

    // Исключение этапа установки также должно запускать обратный откат.
    $GLOBALS['installedModules'] = [];
    $GLOBALS['lifecycleLog'] = [];
    $GLOBALS['APPLICATION']->exceptionMessage = null;
    $installer = new ThrowingEventsInstaller();
    $assert(!$installer->DoInstall(), 'Исключение этапа установки должно вернуть false.');
    $assert(!IsModuleInstalled('vendor.module'), 'После успешного отката исключения регистрация должна быть снята.');
    $assert(
        $GLOBALS['lifecycleLog'] === [
            'install-db',
            'install-events',
            'uninstall-events',
            'uninstall-db',
        ],
        'Исключение этапа установки должно откатывать начатые этапы в обратном порядке.'
    );
    $assert(
        str_contains((string) $GLOBALS['APPLICATION']->exceptionMessage, 'Ошибка регистрации событий.'),
        'Исходное исключение должно передаваться в административный интерфейс.'
    );

    // При неудачном откате регистрация сохраняется для последующего восстановления.
    $GLOBALS['installedModules'] = [];
    $GLOBALS['lifecycleLog'] = [];
    $installer = new FailingRollbackInstaller();
    $assert(!$installer->DoInstall(), 'Неудачный откат не должен считаться успешной установкой.');
    $assert(IsModuleInstalled('vendor.module'), 'При неудачном откате модуль должен остаться зарегистрированным.');
    $assert(
        \Bitrix\Main\Config\Option::get('vendor.module', 'INSTALLATION_INCOMPLETE') === 'Y',
        'При неудачном откате должна сохраниться отметка незавершённой установки.'
    );

    // Исключение во время отката должно сохранять регистрацию и отметку восстановления.
    $GLOBALS['installedModules'] = [];
    $GLOBALS['lifecycleLog'] = [];
    $installer = new ThrowingRollbackInstaller();
    $assert(!$installer->DoInstall(), 'Исключение отката не должно считаться успешной установкой.');
    $assert(IsModuleInstalled('vendor.module'), 'После исключения отката модуль должен остаться зарегистрированным.');
    $assert(
        \Bitrix\Main\Config\Option::get('vendor.module', 'INSTALLATION_INCOMPLETE') === 'Y',
        'После исключения отката должна сохраниться отметка незавершённой установки.'
    );

    // Повторная установка должна завершить ранее прерванные этапы.
    $GLOBALS['lifecycleLog'] = [];
    $installer = new RecoveringInstaller();
    $assert($installer->DoInstall(), 'Повторный запуск должен позволить восстановить частичную установку.');
    $assert(IsModuleInstalled('vendor.module'), 'После восстановления модуль должен оставаться зарегистрированным.');
    $assert(
        $GLOBALS['lifecycleLog'] === ['install-db', 'install-events', 'install-files'],
        'Повторный запуск должен повторно выполнить все этапы установки по порядку.'
    );

    $assert(
        \Bitrix\Main\Config\Option::get('vendor.module', 'INSTALLATION_INCOMPLETE', 'N') !== 'Y',
        'После восстановления отметка незавершённой установки должна быть удалена.'
    );


    // Ошибка восстановления не должна снимать регистрацию ранее установленного модуля.
    $GLOBALS['installedModules'] = ['vendor.module' => true];
    $GLOBALS['lifecycleLog'] = [];
    \Bitrix\\Main\\Config\\Option::set('vendor.module', 'INSTALLATION_INCOMPLETE', 'Y');
    $installer = new FailingEventsInstaller();
    $assert(!$installer->DoInstall(), 'Ошибка восстановления ранее установленного модуля должна вернуть false.');
    $assert(IsModuleInstalled('vendor.module'), 'При успешном откате восстановления существующая регистрация должна сохраниться.');
    $assert(
        \Bitrix\\Main\\Config\\Option::get('vendor.module', 'INSTALLATION_INCOMPLETE') === 'Y',
        'После неудачного восстановления должна сохраниться отметка незавершённой установки.'
    );

    // Сбой записи служебной отметки до начала этапов также не должен снимать существующую регистрацию.
    $GLOBALS['installedModules'] = ['vendor.module' => true];
    $GLOBALS['lifecycleLog'] = [];
    \Bitrix\\Main\\Config\\Option::set('vendor.module', 'INSTALLATION_INCOMPLETE', 'Y');
    \Bitrix\\Main\\Config\\Option::$failNextSet = true;
    $installer = new SuccessfulModuleInstaller();
    $assert(!$installer->DoInstall(), 'Сбой записи отметки восстановления должен прервать установку.');
    $assert(IsModuleInstalled('vendor.module'), 'Сбой записи настройки не должен снимать существующую регистрацию.');
    $assert(
        \Bitrix\\Main\\Config\\Option::get('vendor.module', 'INSTALLATION_INCOMPLETE') === 'Y',
        'Сбой записи не должен удалять ранее существовавшую отметку восстановления.'
    );
    $assert($GLOBALS['lifecycleLog'] === [], 'При сбое записи отметки этапы установки не должны запускаться.');

    // При ошибке удаления регистрация также должна сохраняться.
    $GLOBALS['installedModules'] = ['vendor.module' => true];
    $GLOBALS['lifecycleLog'] = [];
    $installer = new FailingUninstallInstaller();
    $assert(!$installer->DoUninstall(), 'Ошибка удаления должна вернуть false.');
    $assert(IsModuleInstalled('vendor.module'), 'При ошибке удаления регистрация должна сохраниться.');
    $assert(
        $GLOBALS['lifecycleLog'] === ['uninstall-files'],
        'После ошибки удаления последующие этапы не должны запускаться.'
    );

    // Исключение при удалении также должно сохранять регистрацию для повтора.
    $GLOBALS['installedModules'] = ['vendor.module' => true];
    $GLOBALS['lifecycleLog'] = [];
    $installer = new ThrowingUninstallInstaller();
    $assert(!$installer->DoUninstall(), 'Исключение удаления должно вернуть false.');
    $assert(IsModuleInstalled('vendor.module'), 'После исключения удаления модуль должен остаться зарегистрированным.');
    $assert(
        $GLOBALS['lifecycleLog'] === ['uninstall-files'],
        'После исключения удаления последующие этапы не должны запускаться.'
    );

    // Полное удаление должно выполнить все этапы и снять регистрацию.
    $GLOBALS['installedModules'] = ['vendor.module' => true];
    $GLOBALS['lifecycleLog'] = [];
    $installer = new SuccessfulModuleInstaller();
    $assert($installer->DoUninstall(), 'Успешное удаление должно вернуть true.');
    $assert(!IsModuleInstalled('vendor.module'), 'После удаления регистрация должна быть снята.');
    $assert(
        $GLOBALS['lifecycleLog'] === ['uninstall-files', 'uninstall-events', 'uninstall-db'],
        'Этапы удаления выполнены в неверном порядке.'
    );

    echo "Module installer lifecycle smoke test: OK\n";
}
