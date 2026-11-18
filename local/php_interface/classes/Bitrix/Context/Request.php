<?php

declare(strict_types=1);

namespace Project\Bitrix\Context;

use RuntimeException;

/** Фасад над HTTP-запросом текущего контекста Bitrix. */
final class Request extends PerClassBitrixInstance
{
    protected static function createInstance()
    {
        $context = Context::get();

        if (!is_object($context) || !is_callable([$context, 'getRequest'])) {
            throw new RuntimeException('Контекст Bitrix не предоставляет HTTP-запрос.');
        }

        return $context->getRequest();
    }
}
