<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class AIProviderException extends RuntimeException
{
    public static function failed(string $provider, Throwable $previous): self
    {
        return new self(
            sprintf('O provedor de IA "%s" falhou ao analisar a intenção.', $provider),
            0,
            $previous
        );
    }
}
