<?php

declare(strict_types=1);

namespace Project\Bitrix\IBlock\Property;

/**
 * Пользовательское свойство инфоблока «Да/Нет».
 *
 * Хранит значения Y и N в строковом поле и использует штатный контракт
 * пользовательских свойств модуля iblock.
 */
final class YesNoProperty
{
    public const USER_TYPE = 'codematrix_yes_no';

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
            'DESCRIPTION' => 'CodeMatrix: Да/Нет (флажок)',
            'GetPropertyFieldHtml' => [self::class, 'getPropertyFieldHtml'],
            'GetAdminListViewHTML' => [self::class, 'getTextValue'],
            'GetPublicViewHTML' => [self::class, 'getTextValue'],
            'GetAdminFilterHTML' => [self::class, 'getFilterHtml'],
            'GetPublicFilterHTML' => [self::class, 'getFilterHtml'],
            'ConvertToDB' => [self::class, 'convertToDb'],
            'ConvertFromDB' => [self::class, 'convertFromDb'],
            'GetSearchContent' => [self::class, 'getSearchContent'],
            'GetLength' => [self::class, 'getLength'],
        ];
    }

    /**
     * Выводит флажок в форме редактирования свойства.
     *
     * @param array<string, mixed> $property
     * @param array<string, mixed> $value
     * @param array<string, mixed> $control
     */
    public static function getPropertyFieldHtml(array $property, array $value, array $control): string
    {
        $name = self::escape((string) ($control['VALUE'] ?? ''));
        $checked = self::normalizeValue($value['VALUE'] ?? null) === 'Y' ? ' checked' : '';

        return sprintf(
            '<input type="hidden" name="%1$s" value="N"><input type="checkbox" name="%1$s" value="Y"%2$s>',
            $name,
            $checked
        );
    }

    /**
     * Возвращает текстовое представление значения.
     *
     * @param array<string, mixed> $property
     * @param array<string, mixed> $value
     * @param array<string, mixed> $control
     */
    public static function getTextValue(array $property, array $value, array $control = []): string
    {
        return self::normalizeValue($value['VALUE'] ?? null) === 'Y' ? 'Да' : 'Нет';
    }

    /**
     * Выводит фильтр по значению свойства.
     *
     * @param array<string, mixed> $property
     * @param array<string, mixed> $control
     */
    public static function getFilterHtml(array $property, array $control): string
    {
        $name = (string) ($control['VALUE'] ?? '');
        $selectedValue = self::getRequestValue($name);
        $safeName = self::escape($name);

        $options = [
            '' => '(любой)',
            'Y' => 'Да',
            'N' => 'Нет',
        ];

        $html = sprintf('<select name="%s">', $safeName);

        foreach ($options as $optionValue => $label) {
            $selected = $selectedValue === $optionValue ? ' selected' : '';
            $html .= sprintf(
                '<option value="%s"%s>%s</option>',
                self::escape($optionValue),
                $selected,
                self::escape($label)
            );
        }

        return $html . '</select>';
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
        $value['VALUE'] = self::normalizeValue($value['VALUE'] ?? null);

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
        $value['VALUE'] = self::normalizeValue($value['VALUE'] ?? null);

        return $value;
    }

    /**
     * Возвращает текст для поискового индекса.
     *
     * @param array<string, mixed> $property
     * @param array<string, mixed> $value
     * @param array<string, mixed> $control
     */
    public static function getSearchContent(array $property, array $value, array $control = []): string
    {
        return self::getTextValue($property, $value, $control);
    }

    /**
     * Возвращает длину строкового значения.
     *
     * @param array<string, mixed> $property
     * @param array<string, mixed> $value
     */
    public static function getLength(array $property, array $value): int
    {
        return 1;
    }

    private static function normalizeValue(mixed $value): string
    {
        return $value === 'Y' ? 'Y' : 'N';
    }

    private static function getRequestValue(string $name): string
    {
        if ($name === '' || !isset($_REQUEST[$name]) || !is_string($_REQUEST[$name])) {
            return '';
        }

        $value = $_REQUEST[$name];

        return in_array($value, ['Y', 'N'], true) ? $value : '';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
