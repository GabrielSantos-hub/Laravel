<?php

namespace App\Services;

use App\Exceptions\AIProviderException;
use App\Exceptions\InvalidIntentException;
use App\Exceptions\NoCompatibleTemplateException;
use App\Models\Architecture;
use App\Models\Framework;
use App\Models\Language;
use App\Services\AI\IntentAnalyzer;
use App\Services\AI\NullAIProvider;
use App\Services\AI\PromptComposer;
use App\Services\AI\TemplateSelector;
use Illuminate\Database\Eloquent\Model;
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
     * @param  array{language_id?: int|null, framework_id?: int|null, architecture_id?: int|null}  $catalogHints
     * @param  array<string, mixed>  $customVariables  Marcadores dinâmicos do
     *                                                 template preenchidos na tela.
     *
     * @throws InvalidIntentException Entrada vazia ou curta demais.
     * @throws NoCompatibleTemplateException Nenhum template utilizável.
     */
    public function generate(
        string $userInput,
        ?int $forcedTemplateId = null,
        array $catalogHints = [],
        array $customVariables = [],
    ): PromptPipelineResult {
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

        $intent = $this->enrichIntent($intent, $catalogHints);

        $template = $this->selector->select($intent, $forcedTemplateId);

        if ($template === null) {
            throw $forcedTemplateId !== null
                ? NoCompatibleTemplateException::forManualSelection($forcedTemplateId)
                : NoCompatibleTemplateException::forIntent();
        }

        return new PromptPipelineResult(
            prompt: $this->composerFor($offlineProvider)->compose($intent, $template, $customVariables),
            template: $template,
            intent: $intent,
            manualSelection: $forcedTemplateId !== null,
            degraded: $offlineProvider !== null,
        );
    }

    /**
     * Completa a intenção com o que o usuário escolheu nos selects, sem
     * sobrescrever o que o analisador já extraiu do texto.
     *
     * @param  array<string, mixed>  $intent
     * @param  array{language_id?: int|null, framework_id?: int|null, architecture_id?: int|null}  $catalogHints
     * @return array<string, mixed>
     */
    private function enrichIntent(array $intent, array $catalogHints): array
    {
        $technologies = is_array($intent['technologies'] ?? null) ? $intent['technologies'] : [];

        foreach ([
            $this->catalogNome(Language::class, $catalogHints['language_id'] ?? null),
            $this->catalogNome(Framework::class, $catalogHints['framework_id'] ?? null),
        ] as $nome) {
            if ($nome !== null && ! $this->jaListado($technologies, $nome)) {
                $technologies[] = $nome;
            }
        }

        $intent['technologies'] = array_values($technologies);

        if (($intent['architecture'] ?? null) === null) {
            $intent['architecture'] = $this->catalogNome(Architecture::class, $catalogHints['architecture_id'] ?? null);
        }

        return $intent;
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function catalogNome(string $model, mixed $id): ?string
    {
        if (! is_numeric($id)) {
            return null;
        }

        $nome = $model::query()->whereKey((int) $id)->value('nome');

        return is_string($nome) && trim($nome) !== '' ? trim($nome) : null;
    }

    /**
     * @param  array<int, mixed>  $technologies
     */
    private function jaListado(array $technologies, string $nome): bool
    {
        $needle = mb_strtolower($nome);

        foreach ($technologies as $item) {
            if (is_string($item) && mb_strtolower(trim($item)) === $needle) {
                return true;
            }
        }

        return false;
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
