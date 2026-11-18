<?php

declare(strict_types=1);

require_once __DIR__ . '/../local/php_interface/classes/Support/PagePathMatcher.php';

use Project\Support\PagePathMatcher;

function pageExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

// Совпадение каталога и вложенных страниц.
pageExpect(PagePathMatcher::inDirectory('/catalog/item/', '/catalog/'), 'Вложенная страница должна принадлежать каталогу.');
pageExpect(PagePathMatcher::inDirectory('/catalog', 'catalog'), 'Путь без завершающего слеша должен совпадать.');
pageExpect(PagePathMatcher::inDirectory('/catalog/item/', ['/news/', '/catalog/']), 'Должен поддерживаться список каталогов.');
pageExpect(PagePathMatcher::inDirectory('/catalog/item/', '/'), 'Корневой путь должен включать все страницы.');

// Проверка границ сегментов.
pageExpect(!PagePathMatcher::inDirectory('/catalog-old/', '/catalog/'), 'Префикс с другим сегментом не должен совпадать.');
pageExpect(!PagePathMatcher::inDirectory('/catalogue/item/', 'catalog'), 'Похожие имена каталогов не должны совпадать.');
pageExpect(!PagePathMatcher::inDirectory('/shop/catalog/', '/catalog/'), 'Вложенный одноимённый каталог не должен считаться корневым каталогом.');

// Только корневой раздел, без совпадений во вложенных сегментах.
pageExpect(PagePathMatcher::inRootDirectory('/catalog/item/', 'catalog'), 'Корневой раздел должен определяться по первому сегменту.');
pageExpect(PagePathMatcher::inRootDirectory('/CATALOG/item/', '/catalog/'), 'Сопоставление корневого раздела должно быть без учёта регистра.');
pageExpect(PagePathMatcher::inRootDirectory('/catalog/', ['news', 'catalog']), 'Для корневого раздела должен поддерживаться массив.');
pageExpect(!PagePathMatcher::inRootDirectory('/shop/catalog/', 'catalog'), 'Корневой раздел не должен совпадать с вложенным.');
pageExpect(!PagePathMatcher::inRootDirectory('/', 'catalog'), 'Корень сайта не является указанным разделом.');

// Нормализация и некорректные входные значения.
pageExpect(PagePathMatcher::inDirectory('//catalog///item//', 'catalog'), 'Повторные слеши должны нормализоваться.');
pageExpect(!PagePathMatcher::inDirectory('', 'catalog'), 'Пустой текущий путь должен отклоняться.');
pageExpect(!PagePathMatcher::inDirectory('/catalog/?page=2', 'catalog'), 'Входные значения с query string не должны считаться каталогом.');

$invalidArgumentFailed = false;
try {
    PagePathMatcher::inDirectory('/catalog/', ['catalog', 10]);
} catch (InvalidArgumentException $exception) {
    $invalidArgumentFailed = true;
}
pageExpect($invalidArgumentFailed, 'Элементы массива должны быть строками.');

echo "Проверки PagePathMatcher пройдены.\n";
