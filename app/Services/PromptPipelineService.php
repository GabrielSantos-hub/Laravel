<?php

namespace App\Services;

use App\Exceptions\AIProviderException;
use App\Exceptions\InvalidIntentException;
use App\Exceptions\NoCompatibleTemplateException;
use App\Services\AI\IntentAnalyzer;
use App\Services\AI\NullAIProvider;
use App\Services\AI\PromptComposer;
use App\Services\AI\TemplateSelector;
use Psr\Log\LoggerInterface;

/**
 * Orquestra as três etapas do GUEASS num fluxo único:
 *
 *   texto livre -> IntentAnalyzer -> TemplateSelector -> PromptComposer
 *
 * Erros de domínio (entrada inválida, nenhum template compatível) sobem para o
 * controller, que os transforma em mensagem de formulário. Já a indisponibi-
 * lidade do provedor de IA não é problema do usuário: uma AIProviderException
 * derruba a execução para o provedor offline, registra warning e segue. O
 * pipeline nunca devolve erro por causa de LLM fora do ar.
 */
class PromptPipelineService
{
    public function __construct(
        private readonly IntentAnalyzer $analyzer,
        private readonly TemplateSelector $selector,
        private readonly PromptComposer $composer,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * @throws InvalidIntentException Entrada vazia ou curta demais.
     * @throws NoCompatibleTemplateException Nenhum template utilizável.
     */
    public function generate(string $userInput, ?int $forcedTemplateId = null): PromptPipelineResult
    {
        $offlineProvider = null;

        try {
            $intent = $this->analyzer->analyze($userInput);
        } catch (AIProviderException $e) {
            $this->logger?->warning('Análise de intenção via IA falhou; seguindo com o provedor offline.', [
                'exception' => $e->getMessage(),
                'cause' => $e->getPrevious()?->getMessage(),
            ]);

            $offlineProvider = new NullAIProvider;
            $intent = (new IntentAnalyzer($offlineProvider))->analyze($userInput);
        }

        $template = $this->selector->select($intent, $forcedTemplateId);

        if ($template === null) {
            throw $forcedTemplateId !== null
                ? NoCompatibleTemplateException::forManualSelection($forcedTemplateId)
                : NoCompatibleTemplateException::forIntent();
        }

        return new PromptPipelineResult(
            prompt: $this->composerFor($offlineProvider)->compose($intent, $template),
            template: $template,
            intent: $intent,
            manualSelection: $forcedTemplateId !== null,
            degraded: $offlineProvider !== null,
        );
    }

    /**
     * Se a análise já caiu para o modo offline, o provedor remoto está fora do
     * ar: tentar a composição por ele só somaria mais um timeout à espera do
     * usuário. O PromptComposer tem fallback próprio, mas o barato é não
     * chamar.
     */
    private function composerFor(?NullAIProvider $offlineProvider): PromptComposer
    {
        if ($offlineProvider === null) {
            return $this->composer;
        }

        return new PromptComposer($offlineProvider, logger: $this->logger);
    }
}
