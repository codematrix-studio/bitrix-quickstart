<?php

declare(strict_types=1);

namespace Ns\Bitrix\Helper\Files;

use Project\Bitrix\File\FileIconResolver;

/**
 * Совместимость со старым Ns\Bitrix\Helper\Files\Checker.
 */
class Checker
{
    private FileIconResolver $resolver;

    public function __construct(?FileIconResolver $resolver = null)
    {
        $this->resolver = $resolver ?? new FileIconResolver();
    }

    /**
     * Возвращает CSS-класс иконки для файла Bitrix.
     */
    public function chooseFileIcoClass(int $id): string
    {
        return $this->resolver->iconClassForId($id);
    }
}
