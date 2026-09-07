<?php

namespace App\Exceptions;

use InvalidArgumentException;

class InvalidIntentException extends InvalidArgumentException
{
    public static function empty(): self
    {
        return new self('A intenção informada está vazia.');
    }

    public static function tooShort(int $length, int $minimum): self
    {
        return new self(sprintf(
            'A intenção informada é curta demais: %d caractere(s), mínimo de %d.',
            $length,
            $minimum
        ));
    }
}
