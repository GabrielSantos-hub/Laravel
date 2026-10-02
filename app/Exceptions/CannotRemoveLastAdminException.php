<?php

namespace App\Exceptions;

use RuntimeException;

class CannotRemoveLastAdminException extends RuntimeException
{
    public const MESSAGE = 'Não é possível excluir ou rebaixar o último administrador.';

    public static function becauseLastAdmin(): self
    {
        return new self(self::MESSAGE);
    }
}
