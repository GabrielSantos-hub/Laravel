<?php

namespace App\Contracts;

interface AIProviderInterface
{
    /**
     * Analisa a intenção do usuário e devolve os dados estruturados.
     *
     * O array retornado é normalizado pelo IntentAnalyzer, portanto o provedor
     * pode devolver chaves ausentes ou em formato bruto. Em caso de indisponi-
     * bilidade o provedor deve lançar uma exceção em vez de devolver dados
     * inventados.
     *
     * @return array{
     *     objective?: string,
     *     technologies?: array<int, string>|string,
     *     architecture?: string|null,
     *     constraints?: array<int, string>|string,
     *     type?: string
     * }
     */
    public function analyzeIntent(string $userInput): array;

    /**
     * Identificador curto do provedor, usado em logs e mensagens de erro.
     */
    public function name(): string;
}
