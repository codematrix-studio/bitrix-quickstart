<?php

declare(strict_types=1);

require_once __DIR__ . '/../local/php_interface/classes/Support/ArrayPaginator.php';
require_once __DIR__ . '/../local/php_interface/classes/Bitrix/IBlock/ElementView.php';
require_once __DIR__ . '/../local/php_interface/classes/Bitrix/IBlock/SectionView.php';
require_once __DIR__ . '/../local/php_interface/classes/Bitrix/Component/ComponentInvoker.php';

require_once __DIR__ . '/../local/php_interface/legacy/Lema/Base/Paginator.php';
require_once __DIR__ . '/../local/php_interface/legacy/Lema/Base/Component.php';
require_once __DIR__ . '/../local/php_interface/legacy/Lema/Template/Item.php';
require_once __DIR__ . '/../local/php_interface/legacy/Lema/Template/Section.php';

function expectTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

class SmokeComponent extends Project\Bitrix\Component\ComponentInvoker
{
    protected static $componentName = 'test:component';
    protected static $params = ['CACHE_TYPE' => 'A', 'COUNT' => 10];
}

class FakeApplication
{
    public function IncludeComponent(...$arguments)
    {
        return $arguments;
    }

    public function GetCurPageParam($query, $remove)
    {
        return '/catalog/?' . $query;
    }
}

// Array pagination and current-page selection.
$_GET['page'] = '2';
$paginator = new Project\Support\ArrayPaginator(range(1, 25), 10);
$paginator->start(25);
expectTrue($paginator->getElements() === range(11, 20), 'ArrayPaginator must return the requested page.');
expectTrue($paginator->getElements(3) === range(21, 25), 'ArrayPaginator must support an explicit page.');

$_GET['page'] = '99';
$paginator->start(25);
expectTrue($paginator->getElements() === range(21, 25), 'ArrayPaginator must clamp an out-of-range page.');

$thrown = false;
try {
    $paginator->setPerPage(0);
} catch (InvalidArgumentException $exception) {
    $thrown = true;
}
expectTrue($thrown, 'ArrayPaginator must reject a zero page size.');

$_GET['custom_page'] = '2';
$paginator->setPagerName('custom_page')->start(25);
expectTrue($paginator->getElements() === range(11, 20), 'ArrayPaginator must preserve custom pager parameter names.');

// Legacy paginator class name.
$legacyPaginator = \Lema\Base\Paginator::get();
expectTrue($legacyPaginator instanceof Project\Support\ArrayPaginator, 'Legacy paginator adapter must extend the new implementation.');

// Element view compatibility.
$item = new Project\Bitrix\IBlock\ElementView([
    'ID' => 42,
    'NAME' => 'Test item',
    'PROPERTIES' => [
        'PRICE' => ['VALUE' => '150'],
        'DESCRIPTION' => ['VALUE' => ['TEXT' => 'Description']],
    ],
], 'area"42');

expectTrue($item->getId() === 42, 'ElementView must expose the item ID.');
expectTrue($item->propValue('PRICE') === '150', 'ElementView must expose property values.');
expectTrue($item->propText('DESCRIPTION') === 'Description', 'ElementView must expose formatted property text.');
expectTrue($item->editId() === ' id="area&quot;42"', 'ElementView must escape edit-area IDs.');

// Legacy item adapter.
$legacyItem = new \Lema\Template\Item(['ID' => 7, 'NAME' => 'Legacy item']);
expectTrue($legacyItem->getName() === 'Legacy item', 'Legacy item adapter must preserve the old API.');

// Component parameter defaults must not leak between calls.
$defaults = SmokeComponent::getParams();
$merged = SmokeComponent::getParams(['COUNT' => 5, 'FILTER' => 'active']);
expectTrue($merged['COUNT'] === 5 && $merged['CACHE_TYPE'] === 'A', 'ComponentInvoker must merge defaults and overrides.');
expectTrue(SmokeComponent::getParams() === $defaults, 'ComponentInvoker must not mutate static defaults.');

$GLOBALS['APPLICATION'] = new FakeApplication();
$call = SmokeComponent::inc('default', ['COUNT' => 3]);
expectTrue($call[0] === 'test:component' && $call[2]['COUNT'] === 3, 'ComponentInvoker must call the Bitrix component with merged parameters.');

echo "Lema compatibility smoke tests passed.\n";
