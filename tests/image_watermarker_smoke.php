<?php

declare(strict_types=1);

require_once __DIR__ . '/../local/php_interface/classes/Bitrix/IBlock/ImageWatermarker.php';

if (!defined('BX_RESIZE_IMAGE_PROPORTIONAL')) {
    define('BX_RESIZE_IMAGE_PROPORTIONAL', 'proportional');
}

class ImageWatermarkerSmokeCFile
{
    public static array $arguments = [];

    public static function ResizeImageGet(...$arguments): array|false
    {
        self::$arguments = $arguments;

        return ['src' => '/upload/resize_cache/image.jpg', 'width' => 320, 'height' => 240];
    }
}

if (!class_exists('CFile')) {
    class_alias(ImageWatermarkerSmokeCFile::class, 'CFile');
}

function imageWatermarkerExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$watermarker = new Project\Bitrix\IBlock\ImageWatermarker();
$file = ['ID' => 42, 'WIDTH' => 640, 'HEIGHT' => 480];
$watermark = ['file' => '/upload/watermark.png'];

$result = $watermarker->resize($file, $watermark);

imageWatermarkerExpect(is_array($result) && $result['src'] === '/upload/resize_cache/image.jpg', 'Resize must return the Bitrix result.');
imageWatermarkerExpect(ImageWatermarkerSmokeCFile::$arguments[0] === 42, 'Resize must pass the source file ID.');
imageWatermarkerExpect(
    ImageWatermarkerSmokeCFile::$arguments[1] === ['width' => 640, 'height' => 480],
    'Resize must pass the source image dimensions.'
);
imageWatermarkerExpect(ImageWatermarkerSmokeCFile::$arguments[2] === BX_RESIZE_IMAGE_PROPORTIONAL, 'Resize must use the default proportional mode.');
imageWatermarkerExpect(ImageWatermarkerSmokeCFile::$arguments[3] === true, 'Resize must request resulting dimensions.');

$filters = ImageWatermarkerSmokeCFile::$arguments[4];
imageWatermarkerExpect(count($filters) === 1, 'Resize must pass one watermark filter.');
imageWatermarkerExpect($filters[0]['name'] === 'watermark', 'Watermark filter name must be preserved.');
imageWatermarkerExpect($filters[0]['type'] === 'image', 'Watermark filter must default to image type.');
imageWatermarkerExpect($filters[0]['position'] === 'center', 'Watermark position must default to center.');
imageWatermarkerExpect($filters[0]['size'] === 'real', 'Watermark size must default to real.');
imageWatermarkerExpect($filters[0]['file'] === '/upload/watermark.png', 'Watermark file path must be preserved.');

imageWatermarkerExpect(
    $watermarker->getSource($file, $watermark) === '/upload/resize_cache/image.jpg',
    'getSource must return the resized image path.'
);

$missingFileData = $watermarker->resize(['ID' => 42, 'WIDTH' => 640], $watermark);
imageWatermarkerExpect($missingFileData === false, 'Incomplete source file metadata must return false.');

echo "Проверки ImageWatermarker пройдены.\\n";
