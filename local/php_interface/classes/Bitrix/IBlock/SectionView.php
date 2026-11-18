<?php

declare(strict_types=1);

namespace Project\Bitrix\IBlock;

/**
 * Read-only view over a Bitrix section array.
 *
 * Preserves the legacy Lema\\Template\\Section picture conventions.
 */
class SectionView extends ElementView
{
    public function previewPicture($key = 'SRC')
    {
        return $this->getPictureValue('PICTURE', $key);
    }

    public function detailPicture($key = 'SRC')
    {
        $fileId = $this->get('DETAIL_PICTURE');

        if (empty($fileId) || !class_exists('CFile')) {
            return false;
        }

        $path = \\CFile::GetPath($fileId);

        return empty($path) ? null : $path;
    }
}
