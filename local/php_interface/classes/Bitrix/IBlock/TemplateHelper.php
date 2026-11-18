<?php

declare(strict_types=1);

namespace Project\Bitrix\IBlock;

use InvalidArgumentException;
use LogicException;

/**
 * Представление результата компонента Битрикс для шаблона.
 *
 * Регистрирует штатные действия редактирования/удаления и предоставляет
 * элементы или разделы в виде ElementView/SectionView.
 *
 * Ожидает объект CBitrixComponentTemplate (либо совместимый объект) и
 * компонент с публичными arParams/arResult.
 */
class TemplateHelper
{
    private object $template;
    private array $arResult = [];
    private string $collectionKey = 'ITEMS';
    private bool $singleRecord = false;
    private array|ElementView $views = [];

    public function __construct(object $template)
    {
        if (!method_exists($template, 'getComponent')) {
            throw new InvalidArgumentException('Шаблон компонента должен предоставлять метод getComponent().');
        }

        $component = $template->getComponent();
        if (!is_object($component) || !isset($component->arParams) || !isset($component->arResult)) {
            throw new InvalidArgumentException('Компонент должен содержать массивы arParams и arResult.');
        }

        if (!is_array($component->arParams) || !is_array($component->arResult)) {
            throw new InvalidArgumentException('Параметры и результат компонента должны быть массивами.');
        }

        $this->template = $template;
        $this->arResult = $component->arResult;

        $hasItems = array_key_exists('ITEMS', $this->arResult);
        $hasSections = array_key_exists('SECTIONS', $this->arResult);

        if (!$hasItems && !$hasSections) {
            $this->singleRecord = true;
            $this->views = new ElementView($this->arResult);
            return;
        }

        // Сохраняем приоритет ITEMS над SECTIONS, как в старом Lema\Template\TemplateHelper.
        $this->collectionKey = $hasItems ? 'ITEMS' : 'SECTIONS';
        $rows = $this->arResult[$this->collectionKey];

        if (!is_array($rows)) {
            throw new InvalidArgumentException(sprintf('Поле arResult[%s] должно быть массивом.', $this->collectionKey));
        }

        $isSection = $this->collectionKey === 'SECTIONS';
        $this->registerEditActions($component->arParams, $rows, $isSection);

        foreach ($rows as $key => $row) {
            if (!is_array($row) || !isset($row['ID'])) {
                throw new InvalidArgumentException(sprintf('Запись %s в %s должна быть массивом с ID.', (string) $key, $this->collectionKey));
            }

            $editAreaId = $template->GetEditAreaId($row['ID']);
            $this->views[$key] = $isSection
                ? new SectionView($row, $editAreaId)
                : new ElementView($row, $editAreaId);
        }
    }

    /**
     * Возвращает элементы/разделы как объекты либо исходные массивы.
     *
     * @return array<int|string, ElementView|SectionView|array>
     */
    public function items(bool $objectData = true): array
    {
        if ($this->singleRecord) {
            return [];
        }

        return $objectData ? $this->views : $this->arResult[$this->collectionKey];
    }

    /**
     * Алиас items() для шаблонов списков разделов.
     *
     * @return array<int|string, SectionView|array>
     */
    public function sections(bool $objectData = true): array
    {
        return $this->items($objectData);
    }

    /**
     * Возвращает одиночную запись как ElementView.
     */
    public function item(): ElementView
    {
        if (!$this->singleRecord) {
            throw new LogicException('Метод item() доступен только для результата с одной записью.');
        }

        return $this->views;
    }

    public function itemCount(): int
    {
        return $this->singleRecord ? 0 : count($this->arResult[$this->collectionKey]);
    }

    public function get($name)
    {
        return $this->singleRecord ? $this->views->get($name) : ($this->arResult[$name] ?? null);
    }

    public function getId()
    {
        return $this->get('ID');
    }

    public function getName()
    {
        return $this->get('NAME');
    }

    private function registerEditActions(array $params, array $rows, bool $isSection): void
    {
        if (!method_exists($this->template, 'AddEditAction')
            || !method_exists($this->template, 'AddDeleteAction')
            || !method_exists($this->template, 'GetEditAreaId')) {
            throw new LogicException('Объект шаблона не предоставляет полный набор методов действий редактирования Битрикс.');
        }

        if (!class_exists('CIBlock')) {
            throw new LogicException('Для регистрации действий редактирования требуется класс CIBlock (модуль iblock).');
        }

        $iblockId = $params['IBLOCK_ID'] ?? null;
        if ($iblockId === null || $iblockId === '') {
            foreach ($rows as $row) {
                if (is_array($row) && isset($row['IBLOCK_ID'])) {
                    $iblockId = $row['IBLOCK_ID'];
                    break;
                }
            }
        }

        if ($iblockId === null || $iblockId === '') {
            throw new InvalidArgumentException('Не удалось определить IBLOCK_ID для регистрации действий редактирования.');
        }

        $entityName = $isSection ? 'SECTION' : 'ELEMENT';
        $editType = \CIBlock::GetArrayByID($iblockId, $entityName . '_EDIT');
        $deleteType = \CIBlock::GetArrayByID($iblockId, $entityName . '_DELETE');

        $messageCode = $isSection
            ? 'CT_BCSL_ELEMENT_DELETE_CONFIRM'
            : 'CT_BNL_ELEMENT_DELETE_CONFIRM';
        $confirmMessage = function_exists('GetMessage') ? \GetMessage($messageCode) : '';

        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['ID'])) {
                throw new InvalidArgumentException('Каждая запись списка должна быть массивом с ID.');
            }

            $this->template->AddEditAction($row['ID'], $row['EDIT_LINK'] ?? '', $editType);
            $this->template->AddDeleteAction(
                $row['ID'],
                $row['DELETE_LINK'] ?? '',
                $deleteType,
                ['CONFIRM' => $confirmMessage]
            );
        }
    }
}
