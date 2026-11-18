<?php

declare(strict_types=1);

require_once __DIR__ . '/../local/php_interface/classes/Support/PerClassInstance.php';
require_once __DIR__ . '/../local/php_interface/classes/Bitrix/Context/PerClassBitrixInstance.php';
require_once __DIR__ . '/../local/php_interface/classes/Support/PerClassGlobalProxy.php';

require_once __DIR__ . '/../local/php_interface/legacy/Lema/Base/StaticInstance.php';
require_once __DIR__ . '/../local/php_interface/legacy/Lema/Base/BitrixInstances.php';
require_once __DIR__ . '/../local/php_interface/legacy/Lema/Base/GlobalVars.php';

function baseExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

class FirstSingleton extends \Lema\Base\StaticInstance
{
    public string $value = 'first';
}

class SecondSingleton extends \Lema\Base\StaticInstance
{
    public string $value = 'second';
}

class FirstBitrixFacade extends \Lema\Base\BitrixInstances
{
    public static function setInstance()
    {
        static::$instance = (object) ['value' => 'first-bitrix'];
    }
}

class SecondBitrixFacade extends \Lema\Base\BitrixInstances
{
    public static function setInstance()
    {
        static::$instance = (object) ['value' => 'second-bitrix'];
    }
}

class FakeGlobalObject
{
    public function combine($first, $second): string
    {
        return $first . ':' . $second;
    }
}

$GLOBALS['LEMA_GLOBAL_FIRST'] = new FakeGlobalObject();
$GLOBALS['LEMA_GLOBAL_SECOND'] = (object) ['value' => 'second-global'];

class FirstGlobalProxy extends \Lema\Base\GlobalVars
{
    public static function setVarName()
    {
        static::$varName = 'LEMA_GLOBAL_FIRST';
    }
}

class SecondGlobalProxy extends \Lema\Base\GlobalVars
{
    public static function setVarName()
    {
        static::$varName = 'LEMA_GLOBAL_SECOND';
    }
}

$first = FirstSingleton::get();
$second = SecondSingleton::get();
baseExpect($first !== $second, 'StaticInstance must keep one instance per child class.');
baseExpect(FirstSingleton::get() === $first, 'StaticInstance must reuse the same child instance.');

$firstBitrix = FirstBitrixFacade::get();
$secondBitrix = SecondBitrixFacade::get();
baseExpect($firstBitrix->value === 'first-bitrix', 'First Bitrix facade must return its own instance.');
baseExpect($secondBitrix->value === 'second-bitrix', 'Second Bitrix facade must return its own instance.');
baseExpect(FirstBitrixFacade::get()->value === 'first-bitrix', 'Bitrix facade instances must not leak between subclasses.');

baseExpect(FirstGlobalProxy::get() === $GLOBALS['LEMA_GLOBAL_FIRST'], 'First global proxy must resolve its configured global.');
baseExpect(SecondGlobalProxy::get() === $GLOBALS['LEMA_GLOBAL_SECOND'], 'Second global proxy must resolve a different global.');
baseExpect((new FirstGlobalProxy())->combine('one', 'two') === 'one:two', '__call must forward arguments as separate parameters.');
baseExpect(FirstGlobalProxy::get() === $GLOBALS['LEMA_GLOBAL_FIRST'], 'Global proxy must not reuse another subclass object.');

echo "Проверки базовых экземпляров Lema пройдены.\n";
