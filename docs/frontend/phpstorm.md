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
4. Проверьте рабочую директорию. Для приведенных ниже аргументов она не критична, поскольку входной файл задается абсолютным путем.
5. В поле **Arguments** укажите:

```text
"$FilePath$:$ProjectFileDir$/local/assets/css/$FileNameWithoutExtension$.css" --style=compressed
```

6. В поле **Output paths to refresh** укажите:

```text
$ProjectFileDir$/local/assets/css/$FileNameWithoutExtension$.css:$ProjectFileDir$/local/assets/css/$FileNameWithoutExtension$.css.map
```

7. Сохраните настройки и убедитесь, что File Watcher включен.

После сохранения SCSS PhpStorm передаст исходный файл Dart Sass, а результат появится в `local/assets/css/`. Каталог будет создан компилятором, если его еще нет.

## 3. Как организовать исходники

Основной файл проекта — `local/assets/frontend/scss/main.scss`. В нем подключаются partial-файлы через `@use`:

```scss
@use "abstracts/variables";
@use "base/base";
```

Файлы, имя которых начинается с подчеркивания, используются как partial-файлы и не должны становиться самостоятельными CSS-точками входа. Итоговый CSS для страницы — `local/assets/css/main.css`.

Если в проекте появятся дополнительные самостоятельные точки входа, например `catalog.scss` или `contacts.scss`, они будут компилироваться в одноименные CSS-файлы в `local/assets/css/`.

## 4. Важные замечания

- В примере используется синтаксис Dart Sass: входной и выходной файлы передаются одной парой `input:output`, а сжатие задается параметром `--style=compressed`.
- Параметр `--update` из старых примеров относится к другим/устаревшим вариантам Sass CLI. Не добавляйте его в команду Dart Sass.
- **Output paths to refresh** сообщает PhpStorm, где искать результат и source map. Сам путь фактической записи задается в **Arguments**.
- Если в путях есть пробелы, сохраняйте кавычки вокруг пары входного и выходного путей.
- Не редактируйте сгенерированный CSS вручную. Изменяйте SCSS и позволяйте File Watcher обновлять CSS.
- Скомпилированный CSS следует сохранять в репозитории: именно он подключается на рабочем сайте, где компилятор Sass запускаться не обязан.

## Справка JetBrains

- [Sass, SCSS, Less, and PostCSS](https://www.jetbrains.com/help/phpstorm/transpiling-sass-less-and-scss-to-css.html)
- [File Watchers](https://www.jetbrains.com/help/phpstorm/using-file-watchers.html)
