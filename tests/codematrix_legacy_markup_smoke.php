<?php

declare(strict_types=1);

require_once __DIR__ . '/../local/php_interface/classes/Support/PerClassInstance.php';
require_once __DIR__ . '/../local/php_interface/classes/Support/HtmlEscaper.php';
require_once __DIR__ . '/../local/php_interface/classes/Seo/MetaMarkup.php';
require_once __DIR__ . '/../local/php_interface/legacy/CodeMatrix/Legacy/Base/Markup.php';
require_once __DIR__ . '/../local/php_interface/legacy/CodeMatrix/Legacy/Seo/OpenGraph.php';

function markupExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

class FakeApplicationForMarkup
{
    public array $content = [];
    public array $shown = [];

    public function AddViewContent($name, $content): void
    {
        $this->content[$name] = $content;
    }

    public function GetViewContent($name)
    {
        return $this->content[$name] ?? '';
    }

    public function ShowViewContent($name): void
    {
        $this->shown[] = $name;
    }
}

$og = \CodeMatrix\Legacy\Seo\OpenGraph::get();
$og->setItems([
    'title' => '',
    'title2' => 'Заголовок "с кавычками"',
    'description' => '<script>alert(1)</script>',
]);

markupExpect($og->getItem('title') === 'Заголовок "с кавычками"', 'Legacy title2 fallback must be preserved.');
markupExpect($og->getItem('title2') === false, 'Legacy title2 must be removed after fallback.');

$html = $og->getMeta(true);
markupExpect(str_contains($html, 'name="og:title"'), 'OpenGraph prefix must be applied.');
markupExpect(str_contains($html, '&quot;с кавычками&quot;'), 'Meta attribute values must escape quotes.');
markupExpect(!str_contains($html, '<script>'), 'Meta output must escape HTML markup.');

$og->setItem('description', 'Новое описание');
markupExpect($og->getItemMeta('description') !== false, 'getItemMeta must return formatted tag.');

$GLOBALS['APPLICATION'] = new FakeApplicationForMarkup();
$og->setViewContent();
markupExpect(str_contains($GLOBALS['APPLICATION']->content['opengraph'], '<meta name="og:title"'), 'setViewContent must write generated meta tags.');
$og->setImage('/upload/og.jpg');
markupExpect($og->getImage('/default.jpg') === '/upload/og.jpg', 'Image view content must be retrievable.');
$og->show();
markupExpect($GLOBALS['APPLICATION']->shown === ['opengraph'], 'show() must delegate to Bitrix view content.');

echo "Проверки Markup/OpenGraph пройдены.\n";
