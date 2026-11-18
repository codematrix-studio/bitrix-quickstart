# Классы проекта

Размещайте здесь собственные PHP-классы проекта.

Composer PSR-4 в `local/composer.json` связывает namespace `Project\\` с этим каталогом.

Например:

- файл: `local/php_interface/classes/Services/CatalogService.php`
- namespace: `Project\Services`
- класс: `CatalogService`

После добавления или изменения классов обновите автозагрузку:

```bash
composer dump-autoload --working-dir=local
```
