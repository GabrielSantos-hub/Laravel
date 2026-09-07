<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * O pipeline não conseguiu chegar a um template utilizável.
 *
 * É uma falha de domínio esperada, não um erro de infraestrutura: acontece
 * quando a descrição do usuário não casa com nenhum template do catálogo, ou
 * quando o template escolhido à mão sumiu ou foi desativado.
 */
class NoCompatibleTemplateException extends RuntimeException
{
    private function __construct(string $message, private readonly bool $manualSelection)
    {
        parent::__construct($message);
    }

    public static function forManualSelection(int $templateId): self
    {
        return new self(
            sprintf('O template #%d não existe ou está inativo.', $templateId),
            true
        );
    }

    public static function forIntent(): self
    {
        return new self(
            'Nenhum template compatível foi encontrado para essa descrição. '
            .'Detalhe melhor a linguagem, o framework ou a arquitetura desejada.',
            false
        );
    }

    /**
     * Indica se a falha veio de um id escolhido pelo usuário, o que permite
     * apontar o erro para o campo certo do formulário.
     */
    public function wasManualSelection(): bool
    {
        return $this->manualSelection;
    }
}
