<?php

namespace App\Exceptions;

use RuntimeException;

class AvatarRejectedException extends RuntimeException
{
    public const MESSAGE = 'A imagem enviada não pôde ser aceita.';

    public static function invalid(): self
    {
        return new self(self::MESSAGE);
    }
}
