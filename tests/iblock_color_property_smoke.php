<?php

declare(strict_types=1);

require_once __DIR__ . '/../local/php_interface/classes/Bitrix/IBlock/Property/ColorProperty.php';

use Project\Bitrix\IBlock\Property\ColorProperty;

function colorPropertyExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$description = ColorProperty::getUserTypeDescription();

colorPropertyExpect($description['PROPERTY_TYPE'] === 'S', 'Тип хранения должен быть строковым.');
colorPropertyExpect($description['USER_TYPE'] === 'codematrix_color', 'Идентификатор типа должен быть уникальным.');
colorPropertyExpect(
    ($description['GetPropertyFieldHtml'][0] ?? null) === ColorProperty::class,
    'Обработчик формы должен ссылаться на класс свойства.'
);
colorPropertyExpect(
    isset($description['ConvertToDB'], $description['ConvertFromDB'], $description['GetSearchContent']),
    'Описание типа должно содержать обработчики преобразования и поиска.'
);

$html = ColorProperty::getPropertyFieldHtml([], ['VALUE' => 'abc'], ['VALUE' => 'PROPERTY[8][0]']);
colorPropertyExpect(str_contains($html, 'name="PROPERTY[8][0]"'), 'Имя поля должно сохраняться.');
colorPropertyExpect(str_contains($html, 'value="AABBCC"'), 'Короткая HEX-запись должна нормализоваться.');
colorPropertyExpect(str_contains($html, 'type="color"'), 'Форма должна использовать штатный выбор цвета.');

$unsafeHtml = ColorProperty::getPropertyFieldHtml(
    [],
    ['VALUE' => '112233'],
    ['VALUE' => 'field"><script>alert(1)</script>']
);
colorPropertyExpect(!str_contains($unsafeHtml, '<script>alert(1)</script>'), 'Имя поля не должно внедрять HTML или JavaScript.');

colorPropertyExpect(
    ColorProperty::getTextValue([], ['VALUE' => 'a1b2c3']) === '<span style="display:inline-block;width:18px;height:18px;vertical-align:middle;border:1px solid #999;background-color:#A1B2C3"></span> <span>#A1B2C3</span>',
    'Представление цвета должно содержать нормализованный HEX.'
);
colorPropertyExpect(
    ColorProperty::getTextValue([], ['VALUE' => 'not-a-color']) === 'Цвет не выбран',
    'Некорректный цвет не должен попадать в HTML.'
);

$converted = ColorProperty::convertToDb([], ['VALUE' => '#abc', 'DESCRIPTION' => 'Основной цвет']);
colorPropertyExpect($converted['VALUE'] === 'AABBCC', 'Перед сохранением цвет должен нормализоваться.');
colorPropertyExpect($converted['DESCRIPTION'] === 'Основной цвет', 'Остальные поля значения должны сохраняться.');

$restored = ColorProperty::convertFromDb([], ['VALUE' => '12abef']);
colorPropertyExpect($restored['VALUE'] === '12ABEF', 'Значение из хранилища должно нормализоваться.');

$invalid = ColorProperty::convertToDb([], ['VALUE' => 'javascript:alert(1)']);
colorPropertyExpect($invalid['VALUE'] === '', 'Недопустимое значение должно очищаться.');

colorPropertyExpect(
    ColorProperty::getSearchContent([], ['VALUE' => '#abc']) === 'AABBCC',
    'Поисковое значение должно содержать нормализованный HEX.'
);
colorPropertyExpect(ColorProperty::getLength([], ['VALUE' => '#abc']) === 6, 'Длина нормализованного цвета должна быть равна шести.');
colorPropertyExpect(ColorProperty::getLength([], ['VALUE' => '']) === 0, 'Пустой цвет должен иметь нулевую длину.');

$_REQUEST['color_filter'] = '#aabbcc';
$filter = ColorProperty::getFilterHtml([], ['VALUE' => 'color_filter']);
colorPropertyExpect(str_contains($filter, 'value="AABBCC"'), 'Фильтр должен восстанавливать нормализованное значение.');

$_REQUEST['color_filter'] = '<script>';
$filter = ColorProperty::getFilterHtml([], ['VALUE' => 'color_filter']);
colorPropertyExpect(!str_contains($filter, '<script>'), 'Недопустимое значение фильтра не должно попадать в HTML.');

echo "Проверки ColorProperty пройдены.\n";
