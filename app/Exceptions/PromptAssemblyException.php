<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Falha ao montar o envelope profissional do prompt final.
 *
 * Não substitui InvalidIntentException: a intenção já foi aprovada. Aqui o
 * conteúdo da tarefa chegou vazio ou inutilizável para a montagem.
 */
class PromptAssemblyException extends RuntimeException
{
    public static function emptyCore(): self
    {
        return new self('Não é possível montar um prompt profissional sem o conteúdo da tarefa.');
    }
}
