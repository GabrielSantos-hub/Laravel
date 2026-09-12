<?php

namespace App\Exceptions;

use App\Services\PromptGeneratorService;
use InvalidArgumentException;

class InvalidIntentException extends InvalidArgumentException
{
    public static function empty(): self
    {
        return new self('A intenção informada está vazia.');
    }

    public static function unclear(?string $motivo = null): self
    {
        $motivo = is_string($motivo) && trim($motivo) !== ''
            ? trim($motivo)
            : PromptGeneratorService::UNCLEAR_MESSAGE;

        return new self($motivo);
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
