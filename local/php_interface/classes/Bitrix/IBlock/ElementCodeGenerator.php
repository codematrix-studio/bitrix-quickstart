<?php

declare(strict_types=1);

namespace Project\Bitrix\IBlock;

use InvalidArgumentException;
use LogicException;

/**
 * Generates an information-block element code using Bitrix transliteration.
 */
final class ElementCodeGenerator
{
    /**
     * Transliterates a title into a URL-friendly element code.
     *
     * Uses the same replacement rules as the legacy IBlock Text helper.
     * This method does not check whether the generated code is unique.
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
