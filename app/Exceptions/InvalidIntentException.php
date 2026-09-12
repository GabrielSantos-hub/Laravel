<?php

namespace App\Exceptions;

use App\Services\AI\IntentCoherenceChecker;
use InvalidArgumentException;

class InvalidIntentException extends InvalidArgumentException
{
    public static function empty(): self
    {
        return new self('A intenção informada está vazia.');
    }

    public static function unclear(): self
    {
        return new self(IntentCoherenceChecker::UNCLEAR_MESSAGE);
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
