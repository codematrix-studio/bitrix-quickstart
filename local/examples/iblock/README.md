# Инфоблоки

## Дерево разделов

Файл [section-tree.php](section-tree.php) показывает, как собрать дерево разделов инфоблока за один проход по результату `CIBlockSection::GetList()`.

Требуется подключённый модуль `iblock`. Перед вызовом задайте ID инфоблока. Фрагмент возвращает массив, не выводит HTML и не меняет данные.

Источник идеи: `local/codes/blank/compilation/sectionTree.php` в [старом репозитории](https://github.com/ASDAFF/bitrix.quickstart).
