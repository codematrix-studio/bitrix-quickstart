<?php

declare(strict_types=1);

namespace Project\Bitrix\IBlock;

/**
 * Представление массива раздела Bitrix только для чтения.
 *
 * Сохраняет соглашения старого Lema\\Template\\Section для работы с изображениями.
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

        $path = \CFile::GetPath($fileId);

        return empty($path) ? null : $path;
    }
}
