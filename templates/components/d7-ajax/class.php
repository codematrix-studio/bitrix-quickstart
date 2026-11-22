<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\AccessDeniedException;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Engine\ActionFilter\Csrf;
use Bitrix\Main\Engine\ActionFilter\HttpMethod;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\CurrentUser;

/**
 * Заготовка D7-компонента с AJAX-действиями.
 *
 * Перед использованием:
 * - переименуйте класс и компонент;
 * - реализуйте проверку бизнес-прав в canSubmit();
 * - замените демонстрационную проверку входных данных своей логикой;
 * - при изменении данных используйте POST и оставьте CSRF-фильтр включённым.
 *
 * Действие submit не сохраняет данные: оно демонстрирует безопасную
 * структуру контролируемого AJAX-вызова и возвращает только признак приёма.
 *
 * @package Project\Components
 */
class ProjectAjaxComponent extends CBitrixComponent implements Controllerable
{
    /**
     * Настраивает фильтры для AJAX-действий компонента.
     *
     * Для изменяющих состояние действий обязательны POST и проверка CSRF.
     * Не отключайте эти фильтры ради упрощения клиентского вызова.
     *
     * @return array<string, array<string, array<int, object>>>
     */
    public function configureActions(): array
    {
        return [
            'submit' => [
                'prefilters' => [
                    new HttpMethod([HttpMethod::METHOD_POST]),
                    new Csrf(),
                ],
            ],
        ];
    }

    /**
     * Демонстрационное AJAX-действие.
     *
     * @param mixed $message Входное сообщение из AJAX-запроса.
     *
     * @return array{success: true, received: true} Результат успешной проверки.
     *
     * @throws AccessDeniedException Если пользователь не авторизован
     *                                или не имеет необходимого права.
     * @throws ArgumentException Если входные данные имеют неверный формат.
     */
    public function submitAction(mixed $message = ''): array
    {
        $userId = (int) CurrentUser::get()->getId();

        if ($userId <= 0 || !$this->canSubmit($userId)) {
            throw new AccessDeniedException('Недостаточно прав для выполнения действия.');
        }

        if (!is_string($message)) {
            throw new ArgumentException('Параметр message должен быть строкой.', 'message');
        }

        $message = trim($message);

        if ($message === '' || strlen($message) > 255) {
            throw new ArgumentException('Длина параметра message должна составлять от 1 до 255 байт.', 'message');
        }

        // Здесь разместите бизнес-логику. Перед записью повторно проверьте
        // права на конкретный объект и валидируйте все связанные идентификаторы.

        return [
            'success' => true,
            'received' => true,
        ];
    }

    /**
     * Определяет, разрешено ли пользователю выполнять действие.
     *
     * По умолчанию доступ закрыт. Реализуйте проверку прав проекта
     * (например, доступа к конкретному элементу или операции) в наследнике
     * либо непосредственно в этом методе до использования компонента.
     *
     * @param int $userId Идентификатор текущего пользователя.
     *
     * @return bool true, если действие разрешено.
     */
    protected function canSubmit(int $userId): bool
    {
        return false;
    }

    /**
     * AJAX-компонент не формирует HTML при обычном подключении.
     *
     * @return void
     */
    public function executeComponent(): void
    {
    }
}
