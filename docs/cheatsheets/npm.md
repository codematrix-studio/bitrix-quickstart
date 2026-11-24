# npm

## Проверка окружения

```bash
node --version
npm --version
npm help
npm help install
```

Версии Node.js и npm должны соответствовать требованиям проекта. Для фиксации версии Node.js используйте принятую в проекте стратегию (например, `.nvmrc` или поле `engines`).

## Установка и удаление зависимостей

```bash
npm install                  # установить зависимости по package.json и lock-файлу
npm ci                       # чистая установка строго по package-lock.json
npm install package-name     # добавить runtime-зависимость
npm install -D package-name  # добавить dev-зависимость
npm uninstall package-name
npm update                   # обновить в пределах semver-диапазонов package.json
```

Для CI и воспроизводимой чистой установки обычно используйте `npm ci`. Команда требует согласованный `package-lock.json` и удаляет существующий `node_modules`.

После изменения зависимостей проверяйте и коммитьте `package.json` вместе с `package-lock.json`. Не удаляйте lock-файл ради «починки» установки без понимания причины.

## Информация о пакетах

```bash
npm ls --depth=0
npm ls -g --depth=0
npm view package-name version
npm view package-name versions
npm outdated
npm explain package-name
```

## Скрипты package.json

```json
{
  "scripts": {
    "lint": "eslint .",
    "test": "node --test",
    "build": "vite build",
    "check": "npm run lint && npm test"
  }
}
```

Запуск:

```bash
npm run
npm run lint
npm test
npm start
```

npm добавляет локальный каталог `node_modules/.bin` в PATH при запуске скриптов. Поэтому обычно не нужно прописывать полный путь к исполняемому файлу.

## Аудит безопасности

```bash
npm audit
npm audit --omit=dev
```

Отчёт аудита нужно анализировать с учётом используемых пакетов и окружения. Не запускайте `npm audit fix --force` автоматически: команда может установить несовместимые major-версии и изменить дерево зависимостей. Сначала изучите предлагаемые изменения и протестируйте их.

## Полезные файлы

- `package.json` — метаданные, диапазоны версий зависимостей и npm-скрипты.
- `package-lock.json` — зафиксированное дерево зависимостей для воспроизводимой установки.
- `.npmrc` — настройки npm; не переносите глобальные настройки в проект без необходимости.

Не храните токены реестров и приватные credentials в публичном репозитории. Для CI используйте секреты окружения.
