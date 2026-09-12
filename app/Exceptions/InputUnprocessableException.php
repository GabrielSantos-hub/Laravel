<?php

namespace App\Exceptions;

/**
 * Entrada sem sanidade: gibberish, teclado aleatório ou frase sem escopo
 * de software. Falha cedo, antes de inflar um prompt enterprise.
 */
class InputUnprocessableException extends InvalidIntentException
{
    public const MESSAGE = 'A instrução fornecida parece inválida ou desconexa. Por favor, descreva uma necessidade clara.';

    public static function disconnected(): self
    {
        return new self(self::MESSAGE);
    }
}
