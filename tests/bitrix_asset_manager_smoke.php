<?php

declare(strict_types=1);

require_once __DIR__ . '/../local/php_interface/classes/Bitrix/Asset/AssetManager.php';

use Project\Bitrix\Asset\AssetManager;

function assetExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$asset = new class {
    public array $js = [];
    public array $css = [];

    public function addJs(string $file): void
    {
        $this->js[] = $file;
    }

    public function addCss(string $file): void
    {
        $this->css[] = $file;
    }
};

$initialized = [];
$manager = new AssetManager($asset, static function (array $extensions) use (&$initialized): void {
    $initialized = $extensions;
});

$result = $manager
    ->addCss(['/css/site.css', '/css/theme.css'])
    ->init(['fx', 'jquery2'])
    ->addJs('/js/site.js');

assetExpect($result === $manager, 'Методы должны возвращать текущий экземпляр для цепочки вызовов.');
assetExpect($asset->css === ['/css/site.css', '/css/theme.css'], 'CSS-файлы должны передаваться Bitrix Asset в исходном порядке.');
assetExpect($asset->js === ['/js/site.js'], 'JavaScript-файлы должны передаваться Bitrix Asset.');
assetExpect($initialized === ['fx', 'jquery2'], 'Список расширений должен передаваться инициализатору без изменений.');

$manager->addJs([]);
$manager->addCss([]);
assetExpect(count($asset->js) === 1 && count($asset->css) === 2, 'Пустые списки должны быть безопасной операцией.');

foreach ([[''], ['   '], [12], ['ok', false]] as $invalid) {
    $failed = false;

    try {
        $manager->addJs($invalid);
    } catch (InvalidArgumentException) {
        $failed = true;
    }

    assetExpect($failed, 'Некорректные пути ресурсов должны отклоняться до регистрации.');
}

$failed = false;

try {
    (new AssetManager(new stdClass()))->addCss('/css/site.css');
} catch (LogicException) {
    $failed = true;
}
assetExpect($failed, 'Отсутствующий метод Bitrix Asset должен давать понятную ошибку.');

class FakeBitrixAsset
{
    public static ?self $instance = null;
    public array $js = [];
    public array $css = [];

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function addJs(string $file): void
    {
        $this->js[] = $file;
    }

    public function addCss(string $file): void
    {
        $this->css[] = $file;
    }
}

class CJSCore
{
    public static array $initialized = [];

    public static function init(array $extensions): void
    {
        self::$initialized = $extensions;
    }
}

class_alias(FakeBitrixAsset::class, 'Bitrix\\Main\\Page\\Asset');

require_once __DIR__ . '/../local/php_interface/legacy/Lema/Common/Asset.php';
require_once __DIR__ . '/../local/php_interface/legacy/Lema/Common/AssetManager.php';

$legacy = new \Lema\Common\AssetManager();
$legacy
    ->addCssArray(['/legacy/site.css'])
    ->init(['legacy-extension'])
    ->addJsArray(['/legacy/site.js']);

$native = FakeBitrixAsset::getInstance();
assetExpect($native->css === ['/legacy/site.css'], 'Legacy adapter должен передавать CSS в штатный Asset.');
assetExpect($native->js === ['/legacy/site.js'], 'Legacy adapter должен передавать JavaScript в штатный Asset.');
assetExpect(CJSCore::$initialized === ['legacy-extension'], 'Legacy adapter должен вызывать CJSCore::init().');
assetExpect(\Lema\Common\Asset::get() === $native, 'Старый Asset::get() должен возвращать singleton Bitrix Asset.');
assetExpect(\Lema\Common\AssetManager::get() === \Lema\Common\AssetManager::get(), 'Старый AssetManager::get() должен сохранять singleton-контракт.');

echo "Проверки AssetManager и legacy-адаптеров пройдены.\n";
