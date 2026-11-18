<?php

declare(strict_types=1);

require_once __DIR__ . '/../local/php_interface/classes/Bitrix/Asset/AssetManager.php';

use Project\\Bitrix\\Asset\\AssetManager;

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

echo "Проверки AssetManager пройдены.\\n";
