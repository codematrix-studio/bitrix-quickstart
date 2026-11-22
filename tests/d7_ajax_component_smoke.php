<?php

declare(strict_types=1);

namespace Bitrix\Main {
    class AccessDeniedException extends \RuntimeException
    {
    }

    class ArgumentException extends \InvalidArgumentException
    {
        public function __construct(string $message, string $parameter = '')
        {
            parent::__construct($message . ($parameter !== '' ? ' [' . $parameter . ']' : ''));
        }
    }
}

namespace Bitrix\Main\Engine\ActionFilter {
    class Csrf
    {
    }

    class HttpMethod
    {
        public const METHOD_POST = 'POST';

        /** @param list<string> $methods */
        public function __construct(public array $methods)
        {
        }
    }
}

namespace Bitrix\Main\Engine\Contract {
    interface Controllerable
    {
        /** @return array<string, mixed> */
        public function configureActions(): array;
    }
}

namespace Bitrix\Main\Engine {
    class CurrentUser
    {
        private static int $userId = 0;

        public static function setUserId(int $userId): void
        {
            self::$userId = $userId;
        }

        public static function get(): self
        {
            return new self();
        }

        public function getId(): int
        {
            return self::$userId;
        }
    }
}

namespace {
    define('B_PROLOG_INCLUDED', true);

    class CBitrixComponent
    {
    }

    require_once __DIR__ . '/../templates/components/d7-ajax/class.php';

    /**
     * Тестовый компонент с разрешённым действием.
     */
    final class AllowedProjectAjaxComponent extends ProjectAjaxComponent
    {
        protected function canSubmit(int $userId): bool
        {
            return $userId === 42;
        }
    }

    /**
     * Проверяет условие и завершает тест при его нарушении.
     */
    $assert = static function (bool $condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    $component = new AllowedProjectAjaxComponent();
    $actions = $component->configureActions();

    $assert(isset($actions['submit']), 'Действие submit не зарегистрировано.');
    $assert(count($actions['submit']['prefilters']) === 2, 'Для действия должны быть включены оба фильтра.');
    $assert(
        $actions['submit']['prefilters'][0] instanceof \Bitrix\Main\Engine\ActionFilter\HttpMethod,
        'Первым фильтром должен быть HttpMethod.'
    );
    $assert(
        $actions['submit']['prefilters'][0]->methods === ['POST'],
        'Действие должно разрешать только POST.'
    );
    $assert(
        $actions['submit']['prefilters'][1] instanceof \Bitrix\Main\Engine\ActionFilter\Csrf,
        'Вторым фильтром должен быть Csrf.'
    );

    \Bitrix\Main\Engine\CurrentUser::setUserId(0);

    try {
        $component->submitAction('Проверка');
        throw new RuntimeException('Гостевой пользователь не должен выполнять действие.');
    } catch (\Bitrix\Main\AccessDeniedException) {
        // Ожидаемое поведение.
    }

    \Bitrix\Main\Engine\CurrentUser::setUserId(7);

    try {
        $component->submitAction('Проверка');
        throw new RuntimeException('Пользователь без права не должен выполнять действие.');
    } catch (\Bitrix\Main\AccessDeniedException) {
        // Ожидаемое поведение.
    }

    \Bitrix\Main\Engine\CurrentUser::setUserId(42);

    $result = $component->submitAction('Проверка');
    $assert($result === ['success' => true, 'received' => true], 'Некорректный результат успешного действия.');

    foreach ([[], '', str_repeat('x', 256)] as $invalidMessage) {
        try {
            $component->submitAction($invalidMessage);
            throw new RuntimeException('Некорректное сообщение не было отклонено.');
        } catch (\Bitrix\Main\ArgumentException) {
            // Ожидаемое поведение.
        }
    }

    echo "D7 AJAX component smoke test: OK\n";
}
