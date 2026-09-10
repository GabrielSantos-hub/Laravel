<?php

namespace App\Services;

use App\Models\Template;

/**
 * Resultado de uma execução completa do pipeline.
 *
 * Carrega o prompt final e também o caminho percorrido para chegar nele
 * (intenção interpretada e template escolhido), o que permite exibir ou logar
 * a decisão sem reexecutar as etapas.
 */
readonly class PromptPipelineResult
{
    /**
     * @param  array{
     *     objective: string,
     *     technologies: array<int, string>,
     *     architecture: string|null,
     *     constraints: array<int, string>,
     *     type: string
     * }  $intent
     * @param  bool  $degraded  Se o resultado veio do provedor offline porque
     *                          o provedor de IA remoto estava indisponível.
     */
    public function __construct(
        public string $prompt,
        public Template $template,
        public array $intent,
        public bool $degraded = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'prompt' => $this->prompt,
            'template' => [
                'id' => $this->template->getKey(),
                'nome' => $this->template->nome,
                'descricao' => $this->template->descricao,
                'versao' => $this->template->versao,
            ],
            'intent' => $this->intent,
            'degraded' => $this->degraded,
        ];
    }
}
