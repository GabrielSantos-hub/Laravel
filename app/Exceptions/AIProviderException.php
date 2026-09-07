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

    /**
     * Falha em uma operação específica do provedor (análise ou composição).
     *
     * O IntentAnalyzer reembrulha qualquer exceção do provedor com `failed()`,
     * então esta variante existe para a composição, que não passa por lá.
     */
    public static function during(string $provider, string $operation, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('O provedor de IA "%s" falhou na operação "%s".', $provider, $operation),
            0,
            $previous
        );
    }
}
