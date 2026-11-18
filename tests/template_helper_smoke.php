<?php

declare(strict_types=1);

require_once __DIR__ . '/../local/php_interface/classes/Bitrix/IBlock/ElementView.php';
require_once __DIR__ . '/../local/php_interface/classes/Bitrix/IBlock/SectionView.php';
require_once __DIR__ . '/../local/php_interface/classes/Bitrix/IBlock/TemplateHelper.php';
require_once __DIR__ . '/../local/php_interface/legacy/Lema/Template/TemplateHelper.php';

function templateHelperExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

class CIBlock
{
    public static array $calls = [];

    public static function GetArrayByID($iblockId, string $key)
    {
        self::$calls[] = [$iblockId, $key];
        return $key;
    }
}

function GetMessage(string $code): string
{
    return 'Подтверждение: ' . $code;
}

class TemplateHelperComponent
{
    public array $arParams;
    public array $arResult;

    public function __construct(array $params, array $result)
    {
        $this->arParams = $params;
        $this->arResult = $result;
    }
}

class TemplateHelperTemplate
{
    private TemplateHelperComponent $component;
    public array $editActions = [];
    public array $deleteActions = [];

    public function __construct(TemplateHelperComponent $component)
    {
        $this->component = $component;
    }

    public function getComponent(): TemplateHelperComponent
    {
        return $this->component;
    }

    public function AddEditAction($id, $link, $type): void
    {
        $this->editActions[] = [$id, $link, $type];
    }

    public function AddDeleteAction($id, $link, $type, array $params): void
    {
        $this->deleteActions[] = [$id, $link, $type, $params];
    }

    public function GetEditAreaId($id): string
    {
        return 'edit-area-' . $id;
    }
}

$elementTemplate = new TemplateHelperTemplate(new TemplateHelperComponent(
    ['IBLOCK_ID' => 15],
    ['ITEMS' => [
        0 => ['ID' => 101, 'IBLOCK_ID' => 15, 'NAME' => 'Первый', 'EDIT_LINK' => '/edit/101', 'DELETE_LINK' => '/delete/101'],
        1 => ['ID' => 102, 'IBLOCK_ID' => 15, 'NAME' => 'Второй', 'EDIT_LINK' => '/edit/102', 'DELETE_LINK' => '/delete/102'],
    ], 'NAV_RESULT' => 'navigation']
));
$helper = new Project\Bitrix\IBlock\TemplateHelper($elementTemplate);
templateHelperExpect($helper->itemCount() === 2, 'Количество элементов должно совпадать с размером ITEMS.');
templateHelperExpect($helper->items()[0] instanceof Project\Bitrix\IBlock\ElementView, 'Элементы должны возвращаться как ElementView.');
templateHelperExpect($helper->items()[0]->getName() === 'Первый', 'ElementView должен предоставлять данные элемента.');
templateHelperExpect($helper->items()[0]->editId(false) === 'edit-area-101', 'В ElementView должен передаваться ID области редактирования.');
templateHelperExpect($helper->items(false)[1]['ID'] === 102, 'Режим objectData=false должен возвращать исходные массивы.');
templateHelperExpect(count($elementTemplate->editActions) === 2, 'Для каждого элемента должно регистрироваться действие редактирования.');
templateHelperExpect(count($elementTemplate->deleteActions) === 2, 'Для каждого элемента должно регистрироваться действие удаления.');
templateHelperExpect($elementTemplate->deleteActions[0][3]['CONFIRM'] === 'Подтверждение: CT_BNL_ELEMENT_DELETE_CONFIRM', 'Для элементов должен использоваться штатный текст подтверждения.');
templateHelperExpect($helper->get('NAV_RESULT') === 'navigation', 'Метод get() должен читать дополнительные поля результата.');

$sectionTemplate = new TemplateHelperTemplate(new TemplateHelperComponent(
    ['IBLOCK_ID' => 25],
    ['SECTIONS' => [
        ['ID' => 201, 'NAME' => 'Раздел', 'EDIT_LINK' => '/edit/201', 'DELETE_LINK' => '/delete/201'],
    ]]
));
$sectionHelper = new Project\Bitrix\IBlock\TemplateHelper($sectionTemplate);
templateHelperExpect($sectionHelper->sections()[0] instanceof Project\Bitrix\IBlock\SectionView, 'Разделы должны возвращаться как SectionView.');
templateHelperExpect($sectionTemplate->editActions[0][2] === 'SECTION_EDIT', 'Для разделов должен использоваться тип SECTION_EDIT.');
templateHelperExpect($sectionTemplate->deleteActions[0][2] === 'SECTION_DELETE', 'Для разделов должен использоваться тип SECTION_DELETE.');
templateHelperExpect($sectionTemplate->deleteActions[0][3]['CONFIRM'] === 'Подтверждение: CT_BCSL_ELEMENT_DELETE_CONFIRM', 'Для разделов должен использоваться штатный текст подтверждения.');

$singleTemplate = new TemplateHelperTemplate(new TemplateHelperComponent(
    ['IBLOCK_ID' => 15],
    ['ID' => 301, 'NAME' => 'Одиночная запись']
));
$singleHelper = new Project\Bitrix\IBlock\TemplateHelper($singleTemplate);
templateHelperExpect($singleHelper->item() instanceof Project\Bitrix\IBlock\ElementView, 'Одиночный результат должен возвращаться как ElementView.');
templateHelperExpect($singleHelper->getId() === 301 && $singleHelper->getName() === 'Одиночная запись', 'getId()/getName() должны работать для одиночной записи.');
templateHelperExpect($singleHelper->itemCount() === 0, 'Для одиночного результата itemCount() должен возвращать ноль.');
templateHelperExpect($singleHelper->items() === [], 'У одиночного результата нет коллекции items.');

$emptyTemplate = new TemplateHelperTemplate(new TemplateHelperComponent(['IBLOCK_ID' => 15], ['ITEMS' => []]));
$emptyHelper = new Project\Bitrix\IBlock\TemplateHelper($emptyTemplate);
templateHelperExpect($emptyHelper->itemCount() === 0, 'Пустой список должен корректно обрабатываться.');

$missingIblockFailed = false;
try {
    new Project\Bitrix\IBlock\TemplateHelper(new TemplateHelperTemplate(
        new TemplateHelperComponent([], ['ITEMS' => [['ID' => 1]]])
    ));
} catch (InvalidArgumentException $exception) {
    $missingIblockFailed = true;
}
templateHelperExpect($missingIblockFailed, 'При отсутствии IBLOCK_ID должна возникать понятная ошибка.');

$legacyTemplate = new TemplateHelperTemplate(new TemplateHelperComponent(
    ['IBLOCK_ID' => 15],
    ['ITEMS' => [
        ['ID' => 401, 'NAME' => 'Legacy item', 'EDIT_LINK' => '/edit/401', 'DELETE_LINK' => '/delete/401'],
    ]]
));
$legacyHelper = new \Lema\Template\TemplateHelper($legacyTemplate);
templateHelperExpect($legacyHelper->items()[0] instanceof \Lema\Template\Item, 'Legacy adapter должен возвращать старый класс Item.');
templateHelperExpect($legacyHelper->items()[0]->getName() === 'Legacy item', 'Legacy Item должен сохранять доступ к данным.');
templateHelperExpect($legacyHelper->items()[0]->editId(false) === 'edit-area-401', 'Legacy Item должен сохранять editAreaId.');

$legacySingle = new \Lema\Template\TemplateHelper(new TemplateHelperTemplate(
    new TemplateHelperComponent([], ['ID' => 402, 'NAME' => 'Legacy single'])
));
templateHelperExpect($legacySingle->item() instanceof \Lema\Template\Item, 'Legacy item() должен возвращать старый класс Item.');


echo "Проверки TemplateHelper пройдены.\n";
