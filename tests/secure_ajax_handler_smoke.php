<?php

declare(strict_types=1);

require_once __DIR__ . '/../local/php_interface/classes/Bitrix/Ajax/SecureAjaxHandler.php';

use Project\Bitrix\Ajax\SecureAjaxHandler;

function secureAjaxExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$sessionValid = true;
$authenticated = true;
$permissionGranted = true;
$permissionCalls = 0;
$handlerCalls = 0;

$handler = new SecureAjaxHandler(
    static function (?string $token) use (&$sessionValid): bool { return $sessionValid && $token === 'valid-token'; },
    static function () use (&$authenticated): bool { return $authenticated; },
    static function (string $action, array $payload) use (&$permissionCalls, &$permissionGranted): bool {
        $permissionCalls++;

        return $permissionGranted && $action === 'update_profile'
            && ($payload['profile_id'] ?? null) === 15;
    }
);

$handler->register('update_profile', static function (array $payload) use (&$handlerCalls): array {
    $handlerCalls++;
    return ['updated' => true, 'profile_id' => $payload['profile_id']];
});

$handler->register(
    'public_contact',
    static fn (array $payload): array => ['accepted' => isset($payload['message'])],
    requiresAuth: false,
    requiresPermission: false
);

$success = $handler->handle('POST', [
    'action' => 'update_profile',
    'sessid' => 'valid-token',
    'profile_id' => 15,
]);
secureAjaxExpect($success === ['success' => true, 'data' => ['updated' => true, 'profile_id' => 15]], 'Разрешённое действие должно вернуть данные обработчика.');
secureAjaxExpect($handlerCalls === 1, 'Обработчик должен быть вызван ровно один раз.');
secureAjaxExpect($permissionCalls === 1, 'Проверка прав должна выполняться до вызова действия.');

$wrongMethod = $handler->handle('GET', ['action' => 'update_profile', 'sessid' => 'valid-token', 'profile_id' => 15]);
secureAjaxExpect(($wrongMethod['error'] ?? null) === 'method_not_allowed', 'GET-запрос должен быть отклонён.');
secureAjaxExpect($handlerCalls === 1, 'GET не должен вызывать действие.');

$sessionValid = false;
$badSession = $handler->handle('POST', ['action' => 'update_profile', 'sessid' => 'valid-token', 'profile_id' => 15]);
secureAjaxExpect(($badSession['error'] ?? null) === 'invalid_session', 'Запрос с неверной сессией должен быть отклонён.');
$sessionValid = true;

$unknown = $handler->handle('POST', ['action' => 'delete_everything', 'sessid' => 'valid-token']);
secureAjaxExpect(($unknown['error'] ?? null) === 'unknown_action', 'Незарегистрированное действие должно быть отклонено.');

$authenticated = false;
$unauthenticated = $handler->handle('POST', ['action' => 'update_profile', 'sessid' => 'valid-token', 'profile_id' => 15]);
secureAjaxExpect(($unauthenticated['error'] ?? null) === 'authentication_required', 'Защищённое действие должно требовать авторизацию.');
$authenticated = true;

$permissionGranted = false;
$denied = $handler->handle('POST', ['action' => 'update_profile', 'sessid' => 'valid-token', 'profile_id' => 15]);
secureAjaxExpect(($denied['error'] ?? null) === 'access_denied', 'Отказ в доступе должен блокировать действие.');
secureAjaxExpect($handlerCalls === 1, 'Действие не должно запускаться при отказе в доступе.');
$permissionGranted = true;

$public = $handler->handle('POST', [
    'action' => 'public_contact',
    'sessid' => 'valid-token',
    'message' => 'Здравствуйте',
]);
secureAjaxExpect(($public['success'] ?? false) === true, 'Публичное действие может работать без авторизации и объектного разрешения.');
secureAjaxExpect($permissionCalls === 2, 'Для публичного действия не должна запускаться проверка объектных прав.');

$invalidRegistrationFailed = false;
try {
    $handler->register('Bad Action', static fn (): mixed => null);
} catch (InvalidArgumentException) {
    $invalidRegistrationFailed = true;
}
secureAjaxExpect($invalidRegistrationFailed, 'Недопустимое имя действия не должно регистрироваться.');

$duplicateRegistrationFailed = false;
try {
    $handler->register('update_profile', static fn (): mixed => null);
} catch (LogicException) {
    $duplicateRegistrationFailed = true;
}
secureAjaxExpect($duplicateRegistrationFailed, 'Повторная регистрация действия должна завершаться ошибкой.');

echo "Проверки SecureAjaxHandler пройдены.\n";
