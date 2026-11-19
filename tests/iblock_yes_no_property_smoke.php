<?php

declare(strict_types=1);

require_once __DIR__ . '/../local/php_interface/classes/Bitrix/IBlock/Property/YesNoProperty.php';

use Project\Bitrix\IBlock\Property\YesNoProperty;

function yesNoPropertyExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$description = YesNoProperty::getUserTypeDescription();

yesNoPropertyExpect($description['PROPERTY_TYPE'] === 'S', 'Тип хранения должен быть строковым.');
yesNoPropertyExpect($description['USER_TYPE'] === 'codematrix_yes_no', 'Идентификатор пользовательского типа должен быть уникальным.');
yesNoPropertyExpect(
    ($description['GetPropertyFieldHtml'][0] ?? null) === YesNoProperty::class,
    'Обработчик поля должен ссылаться на класс свойства.'
);

$html = YesNoProperty::getPropertyFieldHtml([], ['VALUE' => 'Y'], ['VALUE' => 'PROPERTY[12][0]']);
yesNoPropertyExpect(str_contains($html, 'type="hidden"'), 'Для снятого флажка должно отправляться значение N.');
yesNoPropertyExpect(str_contains($html, 'type="checkbox"'), 'Форма должна содержать флажок.');
yesNoPropertyExpect(str_contains($html, 'checked'), 'Значение Y должно отмечать флажок.');
yesNoPropertyExpect(!str_contains($html, '<script>'), 'HTML формы не должен содержать внедрённую разметку.');

$unsafeName = YesNoProperty::getPropertyFieldHtml(
    [],
    ['VALUE' => 'N'],
    ['VALUE' => 'field"><script>alert(1)</script>']
);
yesNoPropertyExpect(!str_contains($unsafeName, '<script>'), 'Имя поля должно экранироваться.');

yesNoPropertyExpect(
    YesNoProperty::getTextValue([], ['VALUE' => 'Y']) === 'Да',
    'Значение Y должно отображаться как «Да».'
);
yesNoPropertyExpect(
    YesNoProperty::getTextValue([], ['VALUE' => 'N']) === 'Нет',
    'Значение N должно отображаться как «Нет».'
);
yesNoPropertyExpect(
    YesNoProperty::getTextValue([], ['VALUE' => 'invalid']) === 'Нет',
    'Неизвестное значение должно безопасно трактоваться как «Нет».'
);

$converted = YesNoProperty::convertToDb([], ['VALUE' => 'unexpected', 'DESCRIPTION' => 'Комментарий']);
yesNoPropertyExpect($converted['VALUE'] === 'N', 'Перед сохранением неизвестное значение должно нормализоваться в N.');
yesNoPropertyExpect($converted['DESCRIPTION'] === 'Комментарий', 'Описание свойства должно сохраняться.');

$restored = YesNoProperty::convertFromDb([], ['VALUE' => 'Y']);
yesNoPropertyExpect($restored['VALUE'] === 'Y', 'Значение Y должно сохраняться при чтении.');
yesNoPropertyExpect(
    YesNoProperty::getSearchContent([], ['VALUE' => 'Y']) === 'Да',
    'Значение должно иметь текст для поискового индекса.'
);
yesNoPropertyExpect(YesNoProperty::getLength([], ['VALUE' => 'Y']) === 1, 'Длина значения должна быть равна одному символу.');

$_REQUEST['yes_no_filter'] = 'Y';
$filter = YesNoProperty::getFilterHtml([], ['VALUE' => 'yes_no_filter']);
yesNoPropertyExpect(str_contains($filter, 'value="Y" selected'), 'Фильтр должен восстанавливать выбранное значение.');
yesNoPropertyExpect(str_contains($filter, 'value="N"'), 'Фильтр должен предлагать вариант «Нет».');

$_REQUEST['yes_no_filter'] = '<script>';
$filter = YesNoProperty::getFilterHtml([], ['VALUE' => 'yes_no_filter']);
yesNoPropertyExpect(!str_contains($filter, '<script>'), 'Недопустимое значение фильтра не должно попадать в HTML.');

echo "Проверки YesNoProperty пройдены.\n";
