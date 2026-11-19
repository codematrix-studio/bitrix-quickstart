<?php

declare(strict_types=1);

namespace Project\Bitrix\IBlock\Property;

/**
 * Пользовательское свойство инфоблока для хранения цвета в формате HEX.
 *
 * В базе хранится шесть шестнадцатеричных символов без символа #.
 */
final class ColorProperty
{
    public const USER_TYPE = 'codematrix_color';

    /**
     * Возвращает описание типа для события OnIBlockPropertyBuildList.
     *
     * @return array<string, mixed>
     */
    public static function getUserTypeDescription(): array
    {
        return [
            'PROPERTY_TYPE' => 'S',
            'USER_TYPE' => self::USER_TYPE,
            'DESCRIPTION' => 'CodeMatrix: Цвет (HEX)',
            'GetPropertyFieldHtml' => [self::class, 'getPropertyFieldHtml'],
            'GetAdminListViewHTML' => [self::class, 'getTextValue'],
            'GetPublicViewHTML' => [self::class, 'getTextValue'],
            'GetPublicEditHTML' => [self::class, 'getPropertyFieldHtml'],
            'GetAdminFilterHTML' => [self::class, 'getFilterHtml'],
            'GetPublicFilterHTML' => [self::class, 'getFilterHtml'],
            'ConvertToDB' => [self::class, 'convertToDb'],
            'ConvertFromDB' => [self::class, 'convertFromDb'],
            'GetSearchContent' => [self::class, 'getSearchContent'],
            'GetLength' => [self::class, 'getLength'],
        ];
    }

    /**
     * Выводит текстовое поле и штатный браузерный выбор цвета.
     *
     * @param array<string, mixed> $property
     * @param array<string, mixed> $value
     * @param array<string, mixed> $control
     */
    public static function getPropertyFieldHtml(array $property, array $value, array $control): string
    {
        $name = (string) ($control['VALUE'] ?? '');
        $safeName = self::escape($name);
        $normalized = self::normalize($value['VALUE'] ?? '');
        $id = 'cm_color_' . substr(hash('sha256', $name), 0, 16);
        $safeId = self::escape($id);
        $pickerValue = '#' . ($normalized !== '' ? $normalized : '000000');

        return sprintf(
            '<input type="text" name="%1$s" id="%2$s_value" value="%3$s" maxlength="6" pattern="(?:[0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})?" placeholder="RRGGBB" autocomplete="off">'
            . '<input type="color" id="%2$s_picker" value="%4$s" aria-label="Выбрать цвет">'
            . '<script>(function(){'
            . 'var field=document.getElementById("%2$s_value");'
            . 'var picker=document.getElementById("%2$s_picker");'
            . 'if(!field||!picker){return;}'
            . 'picker.addEventListener("input",function(){field.value=picker.value.slice(1).toUpperCase();});'
            . 'field.addEventListener("input",function(){'
            . 'var value=field.value.trim().replace(/^#/,"");'
            . 'if(/^[0-9a-f]{3}$/i.test(value)){value=value.split("").map(function(char){return char+char;}).join("");}'
            . 'if(/^[0-9a-f]{6}$/i.test(value)){picker.value="#"+value;}'
            . '});'
            . '})();</script>',
            $safeName,
            $safeId,
            self::escape($normalized),
            self::escape($pickerValue)
        );
    }

    /**
     * Возвращает безопасное представление цвета.
     *
     * @param array<string, mixed> $property
     * @param array<string, mixed> $value
     * @param array<string, mixed> $control
     */
    public static function getTextValue(array $property, array $value, array $control = []): string
    {
        $color = self::normalize($value['VALUE'] ?? '');

        if ($color === '') {
            return 'Цвет не выбран';
        }

        return sprintf(
            '<span style="display:inline-block;width:18px;height:18px;vertical-align:middle;border:1px solid #999;background-color:#%1$s"></span> <span>#%1$s</span>',
            self::escape($color)
        );
    }

    /**
     * Выводит фильтр по точному HEX-значению.
     *
     * @param array<string, mixed> $property
     * @param array<string, mixed> $control
     */
    public static function getFilterHtml(array $property, array $control): string
    {
        $name = (string) ($control['VALUE'] ?? '');
        $requestValue = '';

        if ($name !== '' && isset($_REQUEST[$name]) && is_string($_REQUEST[$name])) {
            $requestValue = self::normalize($_REQUEST[$name]);
        }

        return sprintf(
            '<input type="text" name="%1$s" value="%2$s" maxlength="6" pattern="(?:[0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})?" placeholder="RRGGBB">',
            self::escape($name),
            self::escape($requestValue)
        );
    }

    /**
     * Нормализует значение перед сохранением.
     *
     * @param array<string, mixed> $property
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    public static function convertToDb(array $property, array $value): array
    {
        $value['VALUE'] = self::normalize($value['VALUE'] ?? '');

        return $value;
    }

    /**
     * Нормализует значение после чтения из хранилища.
     *
     * @param array<string, mixed> $property
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    public static function convertFromDb(array $property, array $value): array
    {
        $value['VALUE'] = self::normalize($value['VALUE'] ?? '');

        return $value;
    }

    /**
     * Возвращает нормализованный цвет для поискового индекса.
     *
     * @param array<string, mixed> $property
     * @param array<string, mixed> $value
     * @param array<string, mixed> $control
     */
    public static function getSearchContent(array $property, array $value, array $control = []): string
    {
        return self::normalize($value['VALUE'] ?? '');
    }

    /**
     * Возвращает длину сохранённого значения.
     *
     * @param array<string, mixed> $property
     * @param array<string, mixed> $value
     */
    public static function getLength(array $property, array $value): int
    {
        return strlen(self::normalize($value['VALUE'] ?? ''));
    }

    private static function normalize(mixed $value): string
    {
        if (!is_string($value) && !is_int($value)) {
            return '';
        }

        $value = strtoupper(ltrim(trim((string) $value), '#'));

        if (preg_match('/^[0-9A-F]{3}$/', $value) === 1) {
            return $value[0] . $value[0] . $value[1] . $value[1] . $value[2] . $value[2];
        }

        return preg_match('/^[0-9A-F]{6}$/', $value) === 1 ? $value : '';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
