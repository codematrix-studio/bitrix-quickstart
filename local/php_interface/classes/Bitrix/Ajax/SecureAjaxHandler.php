<?php

declare(strict_types=1);

namespace Project\Bitrix\Ajax;

use Closure;
use InvalidArgumentException;
use LogicException;

/**
 * Проверяет запрос и направляет его только в явно зарегистрированные AJAX-действия.
 *
 * Не отправляет HTTP-заголовки и не завершает выполнение скрипта:
 * вызывающий код самостоятельно сериализует результат в JSON.
 */
final class SecureAjaxHandler
{
    /** @var array<string, array{handler: Closure, auth: bool, permission: bool}> */
    private array $actions = [];

    private Closure $sessionValidator;
    private Closure $authenticationChecker;
    private Closure $permissionChecker;

    /**
     * @param callable(?string): bool $sessionValidator Проверка CSRF-токена.
     * @param callable(): bool $authenticationChecker Проверка текущего пользователя.
     * @param callable(string, array<string, mixed>): bool $permissionChecker Проверка права на действие и объект.
     */
    public function __construct(
        callable $sessionValidator,
        callable $authenticationChecker,
        callable $permissionChecker
    ) {
        $this->sessionValidator = Closure::fromCallable($sessionValidator);
        $this->authenticationChecker = Closure::fromCallable($authenticationChecker);
        $this->permissionChecker = Closure::fromCallable($permissionChecker);
    }

    /**
     * Политика доступа задаётся только доверенным серверным кодом.
     *
     * @param callable(array<string, mixed>): mixed $handler
     */
    public function register(
        string $name,
        callable $handler,
        bool $requiresAuth = true,
        bool $requiresPermission = true
    ): self {
        if (!$this->isValidActionName($name)) {
            throw new InvalidArgumentException('Недопустимое имя AJAX-действия.');
        }

        if (isset($this->actions[$name])) {
            throw new LogicException(sprintf('AJAX-действие "%s" уже зарегистрировано.', $name));
        }

        $this->actions[$name] = [
            'handler' => Closure::fromCallable($handler),
            'auth' => $requiresAuth,
            'permission' => $requiresPermission,
        ];

        return $this;
    }

    /**
     * Проверяет POST, CSRF, авторизацию и право доступа, затем вызывает действие.
     *
     * @param array<string, mixed> $input
     * @return array{success: bool, data?: mixed, error?: string}
     */
    public function handle(string $method, array $input): array
    {
        if (strtoupper($method) !== 'POST') {
            return $this->failure('method_not_allowed');
        }

        $token = $input['sessid'] ?? null;
        if (!is_string($token) || $token === '' || !($this->sessionValidator)($token)) {
            return $this->failure('invalid_session');
        }

        $actionName = $input['action'] ?? null;
        if (!is_string($actionName) || !$this->isValidActionName($actionName)) {
            return $this->failure('invalid_action');
        }

        $action = $this->actions[$actionName] ?? null;
        if ($action === null) {
            return $this->failure('unknown_action');
        }

        $payload = $input;
        unset($payload['action'], $payload['sessid']);

        if ($action['auth'] && !($this->authenticationChecker)()) {
            return $this->failure('authentication_required');
        }

        if ($action['permission'] && !($this->permissionChecker)($actionName, $payload)) {
            return $this->failure('access_denied');
        }

        return [
            'success' => true,
            'data' => ($action['handler'])($payload),
        ];
    }

    private function isValidActionName(string $name): bool
    {
        return preg_match('/\A[a-z][a-z0-9_.-]{0,63}\z/D', $name) === 1;
    }

    /** @return array{success: false, error: string} */
    private function failure(string $code): array
    {
        return ['success' => false, 'error' => $code];
    }
}
