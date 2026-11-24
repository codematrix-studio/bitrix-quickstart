# Git

## Базовые понятия

- **Рабочая директория** — файлы проекта на диске.
- **Индекс (staging area)** — подготовленные изменения для следующего коммита.
- **Коммит** — зафиксированное состояние проекта.
- **Ветка** — подвижный указатель на коммит.
- **HEAD** — текущая позиция: обычно выбранная ветка и её последний коммит.

Обычный цикл: изменить файлы → проверить diff → добавить нужные изменения в индекс → создать коммит → отправить ветку на сервер.

## Первоначальная настройка

```bash
git config --global user.name "Имя Фамилия"
git config --global user.email "you@example.com"
git config --global init.defaultBranch main
git config --global core.autocrlf true   # типичный вариант для Windows
git config --global --list
```

Настройка `core.autocrlf` зависит от политики окончаний строк в проекте. В репозитории предпочтительно зафиксировать правила через `.gitattributes`, а не полагаться только на глобальную настройку.

## Создание и получение репозитория

```bash
git init
git clone https://github.com/owner/repository.git
git clone https://github.com/owner/repository.git project-dir
git remote -v
git remote add origin https://github.com/owner/repository.git
```

## Проверка состояния и изменений

```bash
git status
git status --short
git diff                    # рабочая директория против индекса
git diff --staged           # индекс против последнего коммита
git diff HEAD               # все отслеживаемые изменения против HEAD
git diff -- path/to/file
git diff main...feature     # изменения feature с точки расхождения с main
git log --oneline --graph --decorate -20
git show --stat
git show <commit>
```

Неотслеживаемые файлы не попадают в обычный `git diff`; их показывает `git status`.

## Индексация и коммиты

```bash
git add path/to/file
git add -p                  # выбрать отдельные фрагменты изменений
git add -A                  # добавить все изменения репозитория
git restore --staged path/to/file
git commit -m "Краткое описание изменения"
git commit
git commit --amend          # изменить последний локальный коммит
```

Перед коммитом проверяйте `git diff --staged`. Не используйте `git add .` автоматически, если в рабочей директории могут быть посторонние файлы.

## Ветки

```bash
git branch                  # локальные ветки
git branch -a               # локальные и удалённые ветки
git switch main
git switch -c feature/name  # создать и переключиться
git branch -d feature/name  # удалить слитую локальную ветку
git branch -D feature/name  # принудительно удалить локальную ветку
git push -u origin feature/name
```

Удалённые ветки:

```bash
git fetch --prune
git push origin --delete feature/name
```

Перед удалением ветки убедитесь, что нужные коммиты сохранены или слиты.

## Синхронизация с сервером

```bash
git fetch origin
git pull --ff-only
git push
git push -u origin HEAD
```

`fetch` загружает сведения и коммиты, не меняя текущую рабочую ветку. `pull` объединяет полученные изменения с текущей веткой. `--ff-only` запрещает неожиданно создавать merge-коммит; при расхождении веток команда остановится, и способ интеграции нужно выбрать осознанно.

## Перенос и объединение изменений

```bash
git merge feature/name
git rebase main
git cherry-pick <commit>
```

Перед `rebase` убедитесь, что переписываемая история не используется другими участниками. Не переписывайте опубликованную общую ветку без согласования.

## Отмена и восстановление

Безопасные типовые операции:

```bash
git restore path/to/file          # вернуть файл к состоянию индекса
git restore --source=HEAD -- path/to/file
git restore --staged path/to/file # убрать файл из индекса, сохранив изменения
git revert <commit>               # новый коммит, отменяющий выбранный коммит
```

Осторожно: команды ниже могут потерять работу или переписать историю.

```bash
git reset --soft HEAD~1    # убрать последний коммит, оставить изменения в индексе
git reset HEAD~1           # убрать коммит, оставить изменения в рабочей директории
git reset --hard HEAD~1    # УДАЛИТЬ локальные изменения и сдвинуть ветку назад
git clean -nd              # только показать неотслеживаемые файлы для удаления
git clean -fd              # удалить неотслеживаемые файлы и каталоги
```

Для уже опубликованных коммитов обычно используйте `git revert`, а не `reset`. Перед любой разрушительной операцией выполните `git status` и при необходимости сохраните работу отдельным коммитом или stash.

## Временное сохранение работы

```bash
git stash push -m "описание"
git stash list
git stash show -p
git stash pop
git stash apply stash@{0}
```

## Полезные настройки

```bash
git config --global pull.ff only
git config --global fetch.prune true
git config --global core.editor "code --wait"
```

Последнюю настройку замените на редактор, который установлен в вашей системе. Не задавайте глобальные настройки, если проект требует иных правил.

## Файл .gitignore

Пример для типового PHP/Node.js проекта (адаптируйте под проект):

```gitignore
/vendor/
/node_modules/
/.env
/.env.*
!/ .env.example
.idea/
.DS_Store
Thumbs.db
```

Строка с исключением должна быть записана без пробела после `!`: `!.env.example`. Файлы, уже добавленные в Git, правилами `.gitignore` автоматически не перестают отслеживаться.
