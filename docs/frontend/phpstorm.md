# Компиляция SCSS в PhpStorm

Для автоматической компиляции SCSS используется встроенный в PhpStorm механизм File Watchers. Отдельная конфигурация npm/Vite/Webpack для этого проекта не требуется.

Инструкция рассчитана на PhpStorm 2025.2 и Dart Sass.

## 1. Установить Dart Sass

Для работы Sass CLI необходим Node.js. Если Dart Sass еще не установлен, его можно установить глобально:

```bash
npm install -g sass
```

Проверьте, что команда доступна в терминале:

```bash
sass --version
```

Если PhpStorm не находит компилятор автоматически, укажите в поле **Program** полный путь к исполняемому файлу `sass.cmd` из каталога глобальных npm-пакетов.

## 2. Создать File Watcher

1. Откройте **Settings → Tools → File Watchers**.
2. Нажмите **+** и выберите шаблон **SCSS**.
3. Убедитесь, что в поле **Program** указан Dart Sass (`sass` или полный путь к `sass.cmd`).
4. В поле **Arguments** укажите:

```text
--update $ProjectFileDir$/local/assets/frontend/scss:$ProjectFileDir$/local/assets/css --style=compressed
```

5. В поле **Output paths to refresh** укажите:

```text
$ProjectFileDir$/local/assets/css/main.css:$ProjectFileDir$/local/assets/css/main.css.map
```

6. В области **Scope** желательно ограничить область наблюдения каталогом `local/assets/frontend/scss/`, чтобы изменения SCSS в других частях проекта не запускали эту компиляцию.
7. Сохраните настройки и убедитесь, что File Watcher включен.

Здесь используется режим компиляции каталога: Sass проверяет исходники в `frontend/scss/` и обновляет соответствующие CSS-файлы в `css/`. Параметр `--update` заставляет компилятор пересобирать только изменившиеся файлы и зависимости. Partial-файлы с начальным подчеркиванием не становятся отдельными CSS-файлами.

## 3. Как организовать исходники

Основной файл проекта — `local/assets/frontend/scss/main.scss`. В нем подключаются partial-файлы через `@use`:

```scss
@use "abstracts/variables" as *;
@use "base/base";
```

Файлы, имя которых начинается с подчеркивания, используются как partial-файлы. Итоговый CSS для страницы — `local/assets/css/main.css`.

Если в проекте появятся дополнительные самостоятельные точки входа, например `catalog.scss` или `contacts.scss`, Sass создаст одноименные CSS-файлы в `local/assets/css/`. Добавьте их CSS и соответствующие карты в **Output paths to refresh**, чтобы PhpStorm отслеживал эти результаты.

## 4. Важные замечания

- Аргументы используют режим Dart Sass для обновления каталога: `--update input-directory:output-directory`. Это поддерживаемая опция Dart Sass, а не устаревший параметр.
- **Output paths to refresh** сообщает PhpStorm, где искать результат и source map. Сам путь фактической записи задается в **Arguments**.
- Не редактируйте сгенерированный CSS вручную. Изменяйте SCSS и позволяйте File Watcher обновлять CSS.
- Скомпилированный CSS следует сохранять в репозитории: именно он подключается на рабочем сайте, где компилятор Sass запускаться не обязан.

## Справка

- [Sass/SCSS в PhpStorm](https://www.jetbrains.com/help/phpstorm/transpiling-sass-less-and-scss-to-css.html)
- [File Watchers в PhpStorm](https://www.jetbrains.com/help/phpstorm/using-file-watchers.html)
- [Dart Sass CLI](https://sass-lang.com/documentation/cli/dart-sass/)
