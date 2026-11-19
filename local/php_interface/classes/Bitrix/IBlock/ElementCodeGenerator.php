<?php

declare(strict_types=1);

namespace Project\Bitrix\IBlock;

use InvalidArgumentException;
use LogicException;

/**
 * Создаёт символьный код элемента инфоблока с помощью транслитерации Bitrix.
 */
final class ElementCodeGenerator
{
    /**
     * Преобразует название в символьный код, пригодный для использования в URL.
     *
     * Использует те же правила замены, что и старый вспомогательный класс IBlock Text.
     * Метод не проверяет уникальность созданного символьного кода.
     */
    public function generate(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            throw new InvalidArgumentException('Text for generating an element code must not be empty.');
        }

        if (!class_exists(\CUtil::class)) {
            throw new LogicException(
                'The Bitrix CUtil class is not available. Load the Bitrix main module before using ElementCodeGenerator.'
            );
        }

        return \CUtil::translit(
            $text,
            'ru',
            [
                'replace_space' => '-',
                'replace_other' => '-',
            ]
        );
    }
}
