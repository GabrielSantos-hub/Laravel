<?php

namespace App\Services;

use App\Exceptions\AIProviderException;
use App\Exceptions\InvalidIntentException;
use App\Exceptions\NoCompatibleTemplateException;
use App\Services\AI\IntentAnalyzer;
use App\Services\AI\PromptComposer;
use App\Services\AI\TemplateSelector;

/**
 * Orquestra as três etapas do GUEASS num fluxo único:
 *
 *   texto livre -> IntentAnalyzer -> TemplateSelector -> PromptComposer
 *
 * O serviço não trata erros por conta própria: ele deixa as exceções de domínio
 * subirem para quem chama (o controller) decidir como apresentá-las. A única
 * falha que ele traduz é o TemplateSelector devolver null, que vira uma
 * NoCompatibleTemplateException com a mensagem adequada ao caso.
 */
class PromptPipelineService
{
    public function __construct(
        private readonly IntentAnalyzer $analyzer,
        private readonly TemplateSelector $selector,
        private readonly PromptComposer $composer,
    ) {}

    /**
     * @throws InvalidIntentException Entrada vazia ou curta demais.
     * @throws NoCompatibleTemplateException Nenhum template utilizável.
     * @throws AIProviderException Provedor de IA indisponível na análise.
     */
    public function generate(string $userInput, ?int $forcedTemplateId = null): PromptPipelineResult
    {
        $intent = $this->analyzer->analyze($userInput);

        $template = $this->selector->select($intent, $forcedTemplateId);

        if ($template === null) {
            throw $forcedTemplateId !== null
                ? NoCompatibleTemplateException::forManualSelection($forcedTemplateId)
                : NoCompatibleTemplateException::forIntent();
        }

        return new PromptPipelineResult(
            prompt: $this->composer->compose($intent, $template),
            template: $template,
            intent: $intent,
            manualSelection: $forcedTemplateId !== null,
        );
    }
}
