# Secure AJAX Handler

Статус: Draft.

## Назначение

Универсальный маршрутизатор POST-действий с обязательной проверкой CSRF, явным реестром действий и серверными проверками авторизации и объектных прав.

## Реализация

Класс Project\Bitrix\Ajax\SecureAjaxHandler находится в local/php_interface/classes/Bitrix/Ajax/SecureAjaxHandler.php.

Класс не отправляет заголовки, не читает суперглобальные массивы и не завершает запрос. Он возвращает массив результата, который контроллер сериализует в JSON.

## Пример интеграции в БУС

    use Project\Bitrix\Ajax\SecureAjaxHandler;

    $ajax = new SecureAjaxHandler(
        static fn (?string $token): bool =>
            is_string($token)
            && function_exists('bitrix_sessid')
            && hash_equals((string) bitrix_sessid(), $token),

        static fn (): bool =>
            isset($GLOBALS['USER'])
            && is_object($GLOBALS['USER'])
            && $GLOBALS['USER']->IsAuthorized(),

        static function (string $action, array $data): bool {
            if ($action === 'update_profile') {
                $profileId = filter_var($data['profile_id'] ?? null, FILTER_VALIDATE_INT);
                if (!is_int($profileId) || $profileId < 1) {
                    return false;
                }

                // Проверить владельца/право на объект через доверенный слой приложения.
                return false;
            }

            return false;
        }
    );

    $ajax->register('update_profile', static function (array $data): array {
        // Повторно валидировать поля и выполнить действие через сервис приложения.
        return ['updated' => true];
    });

    $result = $ajax->handle((string) ($_SERVER['REQUEST_METHOD'] ?? ''), $_POST);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

Пример намеренно возвращает отказ для проверки прав, пока владелец/политика доступа не реализованы в конкретном проекте. Не заменяйте её проверкой только наличия ID.

## Контракт

1. Принимается только POST.
2. CSRF-токен sessid обязателен для всех действий, включая публичные.
3. Клиент может выбрать только ранее зарегистрированное действие.
4. Политика авторизации и объектных прав задаётся при регистрации действия серверным кодом.
5. Поля action и sessid не передаются обработчику.
6. Ошибки возвращаются нейтральными кодами: method_not_allowed, invalid_session, invalid_action, unknown_action, authentication_required, access_denied.
7. Исключения бизнес-обработчика не перехватываются: контроллер должен безопасно залогировать их и вернуть клиенту общее сообщение без внутренних деталей.

Публичное действие можно зарегистрировать с requiresAuth=false и requiresPermission=false, но CSRF-проверка всё равно обязательна.

## Тестирование

Из каталога local:

    composer test:secure-ajax

Smoke-тест проверяет POST-only, CSRF, реестр действий, авторизацию, объектные права и отказ до вызова обработчика.

## Требования и статус

- PHP: 8.1+
- Зависимость от ядра: отсутствует; проверки БУС передаются через callbacks
- Bitrix Verified: интеграция endpoint не проверялась на целевом проекте
- Статус: Draft
