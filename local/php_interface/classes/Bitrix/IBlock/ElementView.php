<?php

declare(strict_types=1);

namespace Project\Bitrix\IBlock;

/**
 * Read-only view over an element or section array returned by Bitrix.
 *
 * The public method names intentionally follow the old Lema\\Template\\Item API.
 */
class ElementView
{
    protected array $arItem = [];
    protected ?string $editAreaId = null;

    public function __construct(array $arItem, $editAreaId = null)
    {
        $this->arItem = $arItem;
        $this->editAreaId = $editAreaId === null ? null : (string) $editAreaId;
    }

    public function get($name)
    {
        return $this->arItem[$name] ?? null;
    }

    public function propEmpty($key): bool
    {
        return empty($this->arItem['PROPERTIES'][$key]['VALUE']);
    }

    public function propFilled($key): bool
    {
        return !$this->propEmpty($key);
    }

    public function prop($name, $key = false)
    {
        if (empty($key)) {
            return $this->arItem['PROPERTIES'][$name] ?? null;
        }

        return $this->arItem['PROPERTIES'][$name][$key] ?? null;
    }

    public function propName($name)
    {
        return $this->prop($name, 'NAME');
    }

    public function propVal($name)
    {
        return $this->prop($name, 'VALUE');
    }

    public function propValue($name)
    {
        return $this->prop($name, 'VALUE');
    }

    public function propText($name)
    {
        $value = $this->propValue($name);

        return is_array($value) ? ($value['TEXT'] ?? null) : null;
    }

    public function propXmlId($name)
    {
        return $this->prop($name, 'VALUE_XML_ID');
    }

    public function propId($name)
    {
        return $this->prop($name, 'ID');
    }

    public function getId()
    {
        return $this->get('ID');
    }

    public function getName()
    {
        return $this->get('NAME');
    }

    public function previewText()
    {
        return $this->get('PREVIEW_TEXT');
    }

    public function detailText()
    {
        return $this->get('DETAIL_TEXT');
    }

    public function listUrl()
    {
        return $this->get('LIST_PAGE_URL');
    }

    public function sectionUrl()
    {
        return $this->get('SECTION_PAGE_URL');
    }

    public function detailUrl()
    {
        return $this->get('DETAIL_PAGE_URL');
    }

    public function previewPicture($key = 'SRC')
    {
        return $this->getPictureValue('PREVIEW_PICTURE', $key);
    }

    public function detailPicture($key = 'SRC')
    {
        return $this->getPictureValue('DETAIL_PICTURE', $key);
    }

    public function editId($withHtml = true)
    {
        if (!$withHtml) {
            return $this->editAreaId;
        }

        if ($this->editAreaId === null) {
            return '';
        }

        return ' id="' . htmlspecialchars($this->editAreaId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
    }

    protected function getPictureValue(string $field, $key)
    {
        $picture = $this->arItem[$field] ?? null;

        if (empty($picture) || !is_array($picture)) {
            return false;
        }

        return empty($picture[$key]) ? null : $picture[$key];
    }
}
