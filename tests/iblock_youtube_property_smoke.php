<?php

declare(strict_types=1);

require_once __DIR__ . '/../local/php_interface/classes/Bitrix/IBlock/Property/YouTubeProperty.php';

use Project\Bitrix\IBlock\Property\YouTubeProperty;

function youtubePropertyExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$description = YouTubeProperty::getUserTypeDescription();

youtubePropertyExpect($description['PROPERTY_TYPE'] === 'S', 'Тип свойства должен хранить строковое значение.');
youtubePropertyExpect($description['USER_TYPE'] === 'codematrix_youtube', 'Идентификатор пользовательского типа должен быть уникальным.');
youtubePropertyExpect(
    ($description['GetPropertyFieldHtml'][0] ?? null) === YouTubeProperty::class,
    'Форма редактирования должна ссылаться на класс свойства.'
);
youtubePropertyExpect(
    isset($description['ConvertToDB'], $description['ConvertFromDB'], $description['GetSearchContent']),
    'Описание типа должно содержать обработчики сохранения, чтения и поиска.'
);

$validLinks = [
    'abcdefghijk' => 'abcdefghijk',
    'https://www.youtube.com/watch?v=abcdefghijk' => 'abcdefghijk',
    'https://youtube.com/watch?feature=share&v=abcdefghijk' => 'abcdefghijk',
    'https://youtu.be/abcdefghijk?t=15' => 'abcdefghijk',
    'https://www.youtube.com/embed/abcdefghijk' => 'abcdefghijk',
    'https://youtube.com/shorts/abcdefghijk' => 'abcdefghijk',
    'https://youtube.com/live/abcdefghijk' => 'abcdefghijk',
    'www.youtube.com/watch?v=abcdefghijk' => 'abcdefghijk',
];

foreach ($validLinks as $input => $expected) {
    $converted = YouTubeProperty::convertToDb([], ['VALUE' => $input]);
    youtubePropertyExpect($converted['VALUE'] === $expected, 'Ссылка YouTube должна преобразовываться в ID: ' . $input);
}

$invalidLinks = [
    '',
    'not-a-video!',
    'https://youtube.com.evil.example/watch?v=abcdefghijk',
    'https://evil.example/watch?v=abcdefghijk',
    'https://youtube.com/watch?v=short',
    'https://youtube.com/watch?v=abcdefghijk<script>',
    'javascript:alert(1)',
    'https://user:pass@youtube.com/watch?v=abcdefghijk',
];

foreach ($invalidLinks as $input) {
    $converted = YouTubeProperty::convertToDb([], ['VALUE' => $input]);
    youtubePropertyExpect($converted['VALUE'] === '', 'Недопустимая ссылка должна отклоняться: ' . $input);
}

$converted = YouTubeProperty::convertToDb([], ['VALUE' => 'https://youtu.be/abcdefghijk', 'DESCRIPTION' => 'Ролик']);
youtubePropertyExpect($converted['DESCRIPTION'] === 'Ролик', 'Описание значения должно сохраняться.');

$restored = YouTubeProperty::convertFromDb([], ['VALUE' => 'abcdefghijk']);
youtubePropertyExpect($restored['VALUE'] === 'abcdefghijk', 'Сохранённый ID должен оставаться неизменным.');

$field = YouTubeProperty::getPropertyFieldHtml(
    [],
    ['VALUE' => 'https://youtu.be/abcdefghijk'],
    ['VALUE' => 'PROPERTY[5][0]']
);
youtubePropertyExpect(str_contains($field, 'name="PROPERTY[5][0]"'), 'Форма должна использовать переданное имя поля.');
youtubePropertyExpect(str_contains($field, 'youtube-nocookie.com/embed/abcdefghijk'), 'Для корректного видео должен выводиться предпросмотр.');
youtubePropertyExpect(str_contains($field, 'https://'), 'Предпросмотр должен использовать HTTPS.');

$unsafeField = YouTubeProperty::getPropertyFieldHtml(
    [],
    ['VALUE' => 'abcdefghijk"><script>alert(1)</script>'],
    ['VALUE' => 'field"><script>alert(2)</script>']
);
youtubePropertyExpect(!str_contains($unsafeField, '<script>'), 'Значение и имя поля не должны внедрять HTML.');

$view = YouTubeProperty::getViewHtml([], ['VALUE' => 'abcdefghijk']);
youtubePropertyExpect(str_contains($view, 'https://www.youtube.com/watch?v=abcdefghijk'), 'Просмотр должен возвращать безопасную ссылку.');
youtubePropertyExpect(str_contains($view, 'rel="noopener noreferrer"'), 'Внешняя ссылка должна быть защищена от доступа к окну-источнику.');
youtubePropertyExpect(YouTubeProperty::getViewHtml([], ['VALUE' => 'invalid']) === '', 'Некорректное значение не должно выводиться.');

youtubePropertyExpect(
    YouTubeProperty::getSearchContent([], ['VALUE' => 'https://youtu.be/abcdefghijk']) === 'abcdefghijk',
    'В поисковый индекс должен попадать ID видео.'
);
youtubePropertyExpect(YouTubeProperty::getLength([], ['VALUE' => 'abcdefghijk']) === 11, 'Длина ID должна быть равна 11.');
youtubePropertyExpect(YouTubeProperty::getLength([], ['VALUE' => 'invalid']) === 0, 'Некорректное значение должно иметь нулевую длину.');

echo "Проверки YouTubeProperty пройдены.\n";
