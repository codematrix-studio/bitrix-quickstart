<?php

declare(strict_types=1);

require_once __DIR__ . '/../local/php_interface/classes/Support/VideoUrlParser.php';

use Project\\Support\\VideoUrlParser;

function videoParserExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$youtube = VideoUrlParser::parse('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10');
videoParserExpect($youtube !== null, 'Обычная ссылка YouTube должна распознаваться.');
videoParserExpect($youtube['provider'] === 'youtube', 'Должен определяться YouTube.');
videoParserExpect($youtube['id'] === 'dQw4w9WgXcQ', 'Должен извлекаться ID YouTube.');
videoParserExpect($youtube['embed_url'] === 'https://www.youtube.com/embed/dQw4w9WgXcQ', 'Должна формироваться ссылка встраивания YouTube.');

$youtubeShort = VideoUrlParser::parse('https://youtu.be/dQw4w9WgXcQ');
videoParserExpect($youtubeShort !== null && $youtubeShort['id'] === 'dQw4w9WgXcQ', 'Короткая ссылка YouTube должна распознаваться.');

$youtubeShorts = VideoUrlParser::parse('https://youtube.com/shorts/dQw4w9WgXcQ');
videoParserExpect($youtubeShorts !== null && $youtubeShorts['id'] === 'dQw4w9WgXcQ', 'Ссылка YouTube Shorts должна распознаваться.');

$youtubeNoCookie = VideoUrlParser::parse('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');
videoParserExpect($youtubeNoCookie !== null && $youtubeNoCookie['provider'] === 'youtube', 'Ссылка YouTube без cookie должна распознаваться.');

$rutubeId = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6';
$rutube = VideoUrlParser::parse('https://rutube.ru/video/' . $rutubeId . '/');
videoParserExpect($rutube !== null && $rutube['provider'] === 'rutube', 'Ссылка Rutube должна распознаваться.');
videoParserExpect($rutube['id'] === $rutubeId, 'Должен извлекаться ID Rutube.');
videoParserExpect($rutube['embed_url'] === 'https://rutube.ru/play/embed/' . $rutubeId . '/', 'Должна формироваться ссылка встраивания Rutube.');

$rutubeEmbed = VideoUrlParser::parse('https://rutube.ru/play/embed/' . $rutubeId . '/');
videoParserExpect($rutubeEmbed !== null && $rutubeEmbed['id'] === $rutubeId, 'Ссылка встраивания Rutube должна распознаваться.');

$vimeo = VideoUrlParser::parse('https://vimeo.com/123456789');
videoParserExpect($vimeo !== null && $vimeo['provider'] === 'vimeo', 'Ссылка Vimeo должна распознаваться.');
videoParserExpect($vimeo['id'] === '123456789', 'Должен извлекаться ID Vimeo.');
videoParserExpect($vimeo['embed_url'] === 'https://player.vimeo.com/video/123456789', 'Должна формироваться ссылка встраивания Vimeo.');

$vimeoPlayer = VideoUrlParser::parse('https://player.vimeo.com/video/123456789');
videoParserExpect($vimeoPlayer !== null && $vimeoPlayer['id'] === '123456789', 'Ссылка плеера Vimeo должна распознаваться.');

foreach ([
    '',
    'not-a-url',
    '//youtu.be/dQw4w9WgXcQ',
    'https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ',
    'https://youtube.com/watch?v=short',
    'https://youtube.com/watch',
    'https://example.com/video/123456789',
    'https://vimeo.com/not-a-number',
    'https://rutube.ru/video/short/',
    'https://user:pass@youtube.com/watch?v=dQw4w9WgXcQ',
    'https://youtube.com:443/watch?v=dQw4w9WgXcQ',
] as $invalidUrl) {
    videoParserExpect(VideoUrlParser::parse($invalidUrl) === null, 'Некорректная или неподдерживаемая ссылка должна отклоняться: ' . $invalidUrl);
}

echo "Проверки VideoUrlParser пройдены.\\n";
