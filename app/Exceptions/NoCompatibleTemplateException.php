<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * O pipeline não conseguiu chegar a um template utilizável.
 *
 * É uma falha de domínio esperada, não um erro de infraestrutura: acontece
 * quando a descrição do usuário não casa com nenhum template do catálogo.
 */
class NoCompatibleTemplateException extends RuntimeException
{
    public static function forIntent(): self
    {
        return new self(
            'Nenhum template compatível foi encontrado para essa descrição. '
            .'Detalhe melhor a linguagem, o framework ou a arquitetura desejada.'
        );
    }
}
