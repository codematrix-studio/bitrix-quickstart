<?php

declare(strict_types=1);

namespace Project\Support;

/** Единая точка JSON-кодирования с сохранением стандартного результата PHP. */
final class JsonEncoder
{
    public static function encode($data, int $options = 0, int $depth = 512)
    {
        return json_encode($data, $options, $depth);
    }
}
