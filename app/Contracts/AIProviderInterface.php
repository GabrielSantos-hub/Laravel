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
     * Funde o corpo de um template com as variáveis já resolvidas da intenção,
     * devolvendo o prompt final pronto para uso.
     *
     * O provedor deve substituir os placeholders `{chave}`, resolver os blocos
     * `{% if chave %}...{% endif %}` e refinar a redação. Em caso de falha deve
     * lançar exceção: quem chama é responsável pelo fallback.
     *
     * @param  string  $instruction  Instrução de sistema descrevendo a tarefa.
     * @param  string  $templateBody  Corpo bruto do template, com placeholders.
     * @param  array<string, string>  $variables  Mapa de placeholder => valor.
     */
    public function composePrompt(string $instruction, string $templateBody, array $variables): string;

    /**
     * Identificador curto do provedor, usado em logs e mensagens de erro.
     */
    public function name(): string;
}
