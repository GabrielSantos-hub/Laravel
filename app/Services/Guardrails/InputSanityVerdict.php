<?php

namespace App\Services\Guardrails;

/**
 * Resultado do guardrail: rejeitar, montar envelope completo ou modo lean.
 */
readonly class InputSanityVerdict
{
    public function __construct(
        public bool $accepted,
        public bool $lean = false,
    ) {}

    public static function reject(): self
    {
        return new self(accepted: false);
    }

    public static function full(): self
    {
        return new self(accepted: true, lean: false);
    }

    public static function lean(): self
    {
        return new self(accepted: true, lean: true);
    }
}
