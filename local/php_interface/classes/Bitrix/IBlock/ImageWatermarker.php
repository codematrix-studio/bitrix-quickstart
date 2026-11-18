<?php

declare(strict_types=1);

namespace Project\Bitrix\IBlock;

use LogicException;

/** Обёртка над CFile::ResizeImageGet для изображений элементов инфоблока. */
final class ImageWatermarker
{
    public function resize(array $element, array $watermarkParams, $resizeType = null)
    {
        if (!isset($element['ID'], $element['WIDTH'], $element['HEIGHT'], $watermarkParams['file'])) {
            return false;
        }

        if (!class_exists('CFile') || !is_callable(['CFile', 'ResizeImageGet'])) {
            throw new LogicException('Класс CFile или метод ResizeImageGet недоступен.');
        }

        if ($resizeType === null) {
            if (!defined('BX_RESIZE_IMAGE_PROPORTIONAL')) {
                throw new LogicException('Не задан тип масштабирования изображения.');
            }
            $resizeType = constant('BX_RESIZE_IMAGE_PROPORTIONAL');
        }

        $defaults = [
            'name' => 'watermark',
            'position' => 'center',
            'size' => 'real',
        ];

        $watermark = array_replace($defaults, $watermarkParams);

        return \CFile::ResizeImageGet(
            $element['ID'],
            ['width' => $element['WIDTH'], 'height' => $element['HEIGHT']],
            $resizeType,
            true,
            [$watermark]
        );
    }

    public function getSource(array $element, array $watermarkParams, $resizeType = null)
    {
        $result = $this->resize($element, $watermarkParams, $resizeType);

        return is_array($result) && isset($result['src']) ? $result['src'] : false;
    }
}
