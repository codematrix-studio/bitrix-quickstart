<?php

declare(strict_types=1);

namespace Project\Validation;

use LogicException;

/**
 * Bitrix-адаптер формы: отправка почтового события и добавление элемента инфоблока.
 *
 * Сохраняет методы Lema\\Forms\\AjaxForm. Не выполняет автоматическую отправку
 * формы и не заменяет проверку CSRF/прав доступа в обработчике запроса.
 */
class AjaxForm extends FormValidator
{
    protected $iblockId = null;

    public function sendMessage($event, array $data, $siteId = 's1'): bool
    {
        if (!is_string($event) || trim($event) === '' || !is_string($siteId) || trim($siteId) === '') {
            $this->setError('email_send', 'Не указано почтовое событие или сайт');
            return false;
        }

        if (!class_exists('CEvent') || !is_callable(['CEvent', 'Send'])) {
            $this->setError('email_send', 'Почтовый модуль Битрикс недоступен');
            return false;
        }

        try {
            $result = \CEvent::Send($event, $siteId, $data);
        } catch (\Throwable $exception) {
            $this->setError('email_send', 'Произошла ошибка при отправке сообщения');
            return false;
        }

        if ($result === false || $result === null) {
            $this->setError('email_send', 'Произошла ошибка при отправке сообщения');
            return false;
        }

        return true;
    }

    public function addRecord($iblockId, array $data): bool
    {
        if (!is_scalar($iblockId) || (int) $iblockId < 1) {
            $this->setError('empty_iblock', 'Не указан инфоблок');
            return false;
        }

        if (!class_exists('Bitrix\\Main\\Loader') || !\Bitrix\Main\Loader::includeModule('iblock')) {
            $this->setError('add_record', 'Модуль инфоблоков Битрикс недоступен');
            return false;
        }

        if (!class_exists('CIBlockElement')) {
            $this->setError('add_record', 'Класс элемента инфоблока недоступен');
            return false;
        }

        $element = new \CIBlockElement();
        // Идентификатор инфоблока задаётся аргументом метода, а не данными формы.
        $fields = array_replace($data, ['IBLOCK_ID' => (int) $iblockId]);

        try {
            $result = $element->Add($fields);
        } catch (\Throwable $exception) {
            $this->setError('add_record', 'Произошла ошибка при добавлении записи');
            return false;
        }

        if ($result === false) {
            $this->setError('add_record', 'Произошла ошибка при добавлении записи: ' . (string) ($element->LAST_ERROR ?? ''));
            return false;
        }

        return true;
    }

    public function formActionFull($iblockId, array $addParams, $event, array $messageParams = [], $siteId = 's1'): bool
    {
        // Порядок действий сохранён для совместимости: сначала почтовое событие, затем запись.
        return $this->sendMessage($event, $messageParams, $siteId)
            && $this->addRecord($iblockId, $addParams);
    }
}
