<?php

/**
 * Совместимый фасад старого Lema\\Common\\Helper.
 * Подключать после Composer autoload.
 */

namespace Lema\Common;

use InvalidArgumentException;
use LogicException;
use Project\Bitrix\IBlock\ImageWatermarker;
use Project\Bitrix\IBlock\PropertyAccessor;
use Project\Support\CharsetConverter;
use Project\Support\HtmlEscaper;
use Project\Support\JsonEncoder;
use Project\Support\RussianPluralizer;
use Project\Support\UrlGenerator;

abstract class Helper
{
    private static ?UrlGenerator $urlGenerator = null;

    public static function pluralize($n, array $items)
    {
        return RussianPluralizer::word($n, $items);
    }

    public static function pluralizeN($n, array $items, $splitSym = ' ')
    {
        return RussianPluralizer::withNumber($n, $items, $splitSym);
    }

    public static function enc($value, $flags = ENT_COMPAT)
    {
        if ($value === null) {
            $value = '';
        } elseif (!is_scalar($value)) {
            throw new InvalidArgumentException('Для HTML-кодирования ожидается скалярное значение.');
        }

        return (new HtmlEscaper())->escape((string) $value, (int) $flags, 'UTF-8');
    }

    public static function getJson($data, $options = 0, $depth = 512)
    {
        return JsonEncoder::encode($data, (int) $options, (int) $depth);
    }

    public static function utf8ToCP1251($value)
    {
        if (!is_string($value)) {
            return false;
        }

        return CharsetConverter::toWindows1251($value);
    }

    /**
     * Устанавливает доверенный адрес сайта. Не берёт домен из HTTP_HOST.
     */
    public static function setBaseUrl(string $baseUrl): void
    {
        static::$urlGenerator = new UrlGenerator($baseUrl);
    }

    public static function getFullUrl($url)
    {
        if (static::$urlGenerator === null) {
            throw new LogicException(
                'Перед вызовом getFullUrl() задайте доверенный адрес сайта через Helper::setBaseUrl().'
            );
        }

        return static::$urlGenerator->absolute((string) $url);
    }

    public static function getImageWatermark(array $element = [], array $watermarkParams = [], $resizeType = null)
    {
        return (new ImageWatermarker())->resize($element, $watermarkParams, $resizeType);
    }

    public static function getImageWatermarkSrc(array $element = [], array $watermarkParams = [], $resizeType = null)
    {
        return (new ImageWatermarker())->getSource($element, $watermarkParams, $resizeType);
    }

    public static function propEmpty($code, array $data = [], $propArrKey = 'PROPERTIES')
    {
        return PropertyAccessor::isEmpty((string) $code, $data, (string) $propArrKey);
    }

    public static function propFilled($code, array $data = [], $propArrKey = 'PROPERTIES')
    {
        return PropertyAccessor::isFilled((string) $code, $data, (string) $propArrKey);
    }

    public static function prop($code, array $data = [], $propArrKey = 'PROPERTIES')
    {
        return PropertyAccessor::get((string) $code, $data, (string) $propArrKey);
    }

    public static function propValue($code, array $data = [], $propArrKey = 'PROPERTIES')
    {
        return PropertyAccessor::getValue((string) $code, $data, (string) $propArrKey);
    }

    public static function escPropValue($code, array $data = [], $propArrKey = 'PROPERTIES', $encodeFlags = ENT_COMPAT)
    {
        $value = static::propValue($code, $data, $propArrKey);

        return empty($value) ? null : static::enc($value, $encodeFlags);
    }
}
