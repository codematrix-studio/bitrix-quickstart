<?php

declare(strict_types=1);

require_once __DIR__ . '/../local/php_interface/classes/Bitrix/Asset/AssetManager.php';
require_once __DIR__ . '/../local/php_interface/legacy/Lema/Common/AssetManager.php';



class AssetManagerChildOne extends \Lema\Common\AssetManager {}
class AssetManagerChildTwo extends \Lema\Common\AssetManager {}

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
$manager = new \Project\Bitrix\Asset\AssetManager($asset, static function (array $extensions) use (&$initialized): void {
    $initialized = $extensions;
});

$result = $manager
    ->addCss('/local/templates/site/style.css')
    ->addCss(['/local/templates/site/theme.css', '/local/templates/site/mobile.css'])
    ->init(['jquery2', 'popup'])
    ->addJs('/local/templates/site/app.js')
    ->addJsArray(['/local/templates/site/catalog.js']);

assetExpect($result === $manager, 'Методы должны поддерживать цепочку вызовов.');
assetExpect($asset->css === [
    '/local/templates/site/style.css',
    '/local/templates/site/theme.css',
    '/local/templates/site/mobile.css',
], 'CSS-файлы должны передаваться Bitrix Asset в исходном порядке.');
assetExpect($asset->js === [
    '/local/templates/site/app.js',
    '/local/templates/site/catalog.js',
], 'JS-файлы должны передаваться Bitrix Asset в исходном порядке.');
assetExpect($initialized === ['jquery2', 'popup'], 'Список расширений должен передаваться инициализатору без изменений.');

$emptyAsset = new class {
    public function addJs(string $file): void {}
    public function addCss(string $file): void {}
};
$emptyManager = new \\Project\\Bitrix\\Asset\\AssetManager($emptyAsset, static function (array $extensions): void {});
$emptyManager->addJs([])->addCss([]);

$invalidFileFailed = false;
try {
    $manager->addJs(['/valid.js', '']);
} catch (InvalidArgumentException) {
    $invalidFileFailed = true;
}
assetExpect($invalidFileFailed, 'Пустые пути к файлам должны отклоняться.');

$invalidExtensionFailed = false;
try {
    $manager->init(['jquery2', 10]);
} catch (InvalidArgumentException) {
    $invalidExtensionFailed = true;
}
assetExpect($invalidExtensionFailed, 'Некорректные имена расширений должны отклоняться.');

$invalidAssetFailed = false;
try {
    new \\Project\\Bitrix\\Asset\\AssetManager(new stdClass());
} catch (InvalidArgumentException) {
    $invalidAssetFailed = true;
}
assetExpect($invalidAssetFailed, 'Объект без методов addJs/addCss должен отклоняться.');

$missingBitrixFailed = false;
try {
    (new \\Project\\Bitrix\\Asset\\AssetManager())->addJs('/local/app.js');
} catch (RuntimeException $exception) {
    $missingBitrixFailed = str_contains($exception->getMessage(), 'Bitrix');
}
assetExpect($missingBitrixFailed, 'Без ядра Bitrix должна возвращаться понятная ошибка.');

$legacyFirst = \Lema\Common\AssetManager::get();
assetExpect(
    $legacyFirst === \Lema\Common\AssetManager::get(),
    'Повторный вызов get() должен возвращать singleton одного класса.'
);

$childOne = AssetManagerChildOne::get();
$childTwo = AssetManagerChildTwo::get();
assetExpect($childOne instanceof AssetManagerChildOne, 'Наследник должен получать экземпляр собственного класса.');
assetExpect($childTwo instanceof AssetManagerChildTwo, 'Второй наследник должен получать экземпляр собственного класса.');
assetExpect($childOne !== $childTwo, 'Разные наследники не должны разделять singleton.');

AssetManagerChildOne::resetInstance();
assetExpect(
    AssetManagerChildTwo::get() === $childTwo,
    'Сброс singleton одного наследника не должен затрагивать другой класс.'
);
$childOneAfterReset = AssetManagerChildOne::get();
assetExpect(
    $childOneAfterReset instanceof AssetManagerChildOne && $childOneAfterReset !== $childOne,
    'После resetInstance() должен создаваться новый экземпляр.'
);
assetExpect(
    \Lema\Common\AssetManager::get() === $legacyFirst,
    'Сброс singleton наследника не должен затрагивать экземпляр базового адаптера.'
);

echo "Проверки AssetManager пройдены.\n";
