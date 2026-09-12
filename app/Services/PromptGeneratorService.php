<?php

namespace App\Services;

use App\Contracts\AIProviderInterface;
use App\Exceptions\AIProviderException;
use App\Exceptions\InputUnprocessableException;
use App\Exceptions\InvalidIntentException;
use App\Exceptions\NoCompatibleTemplateException;
use App\Exceptions\PromptAssemblyException;
use App\Models\Architecture;
use App\Models\Framework;
use App\Models\Language;
use App\Models\Template;
use App\Services\AI\IntentAnalyzer;
use App\Services\AI\IntentSynthesizer;
use App\Services\AI\NullAIProvider;
use App\Services\AI\PromptComposer;
use App\Services\AI\TemplateInterpolator;
use App\Services\AI\TemplateSelector;
use App\Services\Guardrails\InputSanityGuardrail;
use Illuminate\Database\Eloquent\Model;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Gera o prompt numa única chamada de IA com saída JSON estruturada:
 * validação semântica + texto final no mesmo payload.
 */
class PromptGeneratorService
{
    public const UNCLEAR_MESSAGE = 'Não conseguimos identificar uma instrução ou objetivo claro de software no seu texto. Por favor, descreva de forma mais detalhada o que você deseja construir.';

    public const SYSTEM_INSTRUCTION = <<<'TXT'
        Você é o Gatekeeper de Qualidade do GUEASS. Sua função é APROVAR (`valido: true`) ou REJEITAR (`valido: false`) a intenção do usuário.

        Para ser APROVADA, a entrada DEVE satisfazer OBRIGATORIAMENTE OS DOIS CRITÉRIOS ABAIXO. Se qualquer um falhar, a entrada DEVE SER REJEITADA.

        ---

        #### CRITÉRIO 1: PURIDADE DO TEXTO (Não-Contaminação)
        - A entrada NÃO PODE conter sequências aleatórias de teclado (ex: "asdfgh", "lkjhgf", "zxcvbnm").
        - A presença de termos técnicos (como "docker" ou "mysql") NÃO anula nem perdoa a presença de ruídos ou digitações aleatórias na mesma frase.
        - Se houver lixo de digitação em qualquer parte do texto -> REJEITE IMMEDIATAMENTE (`valido: false`).

        #### CRITÉRIO 2: INTENÇÃO EXPLÍCITA DE SOFTWARE
        - O objetivo CENTRAL do texto precisa ser a construção, especificação, teste, análise ou manutenção de um SOFTWARE, SISTEMA ou REGRA DE NEGÓCIO.
        - Frases cotidianas, conversas informais, poesias, receitas ou listas de objetos sem propósito de sistema (ex: "hoje o dia está bonito para comer bola", "abacaxi relógio girassol") NÃO possuem intenção de software.
        - Estar gramaticalmente correto NÃO é suficiente. Se não houver escopo de desenvolvimento -> REJEITE IMMEDIATAMENTE (`valido: false`).

        ---

        ### FORMATO OBRIGATÓRIO DE RESPOSTA (JSON APENAS):
        {
          "valido": false,
          "motivo_rejeicao": "Escreva uma mensagem amigável explicando se o problema foi ruído no texto ou falta de um objetivo claro de software.",
          "prompt_gerado": null
        }

        Se OS DOIS critérios passarem, responda somente:
        {
          "valido": true,
          "motivo_rejeicao": null,
          "prompt_gerado": "Texto fluido e sintetizado do prompt final..."
        }

        Não escreva nada fora do objeto JSON. Não invente analogias nem sistemas fictícios para ruído, comida ou objetos aleatórios da vida real.
        TXT;

    public function __construct(
        private readonly AIProviderInterface $provider,
        private readonly TemplateSelector $selector,
        private readonly PromptComposer $composer,
        private readonly ?LoggerInterface $logger = null,
        private readonly PromptBuilderService $builder = new PromptBuilderService,
        private readonly InputSanityGuardrail $guardrail = new InputSanityGuardrail,
    ) {}

    /**
     * @param  array{language_id?: int|null, framework_id?: int|null, architecture_id?: int|null}  $catalogHints
     * @param  array<string, mixed>  $customVariables
     */
    public function generate(
        string $userInput,
        array $catalogHints = [],
        array $customVariables = [],
    ): PromptPipelineResult {
        $verdict = $this->guardrail->assess($userInput);

        if (! $verdict->accepted) {
            throw InputUnprocessableException::disconnected();
        }

        $localAnalyzer = new IntentAnalyzer(new NullAIProvider);
        $intent = $this->enrichIntent($localAnalyzer->analyze($userInput), $catalogHints);

        $template = $this->selector->select($intent);
        $templateBody = $template !== null
            ? (string) $template->corpo_template
            : 'Tarefa: {user_input}';

        $variables = $this->variables($intent, $customVariables, $userInput);

        try {
            $rawAiResponse = $this->provider->generateStructuredPrompt(
                $userInput,
                $templateBody,
                $variables,
            );
        } catch (AIProviderException|Throwable $e) {
            $this->logger?->warning('Geração estruturada via IA falhou; recusando por fail-closed.', [
                'exception' => $e->getMessage(),
                'cause' => $e->getPrevious()?->getMessage() ?? $e->getMessage(),
            ]);

            throw InvalidIntentException::unclear();
        }

        $payload = $this->decodeStructuredResponse($rawAiResponse);

        if (true !== $payload['valido']) {
            throw InvalidIntentException::unclear($payload['motivo_rejeicao']);
        }

        if ($template === null) {
            throw NoCompatibleTemplateException::forIntent();
        }

        $prompt = $verdict->lean ? '' : $payload['prompt_gerado'];

        if ($prompt === '' && ! $verdict->lean) {
            $prompt = $this->composer->compose($intent, $template, $customVariables, $userInput);
        }

        return new PromptPipelineResult(
            prompt: $this->assembleProfessionalPrompt($prompt, $intent, $template, $userInput),
            template: $template,
            intent: $intent,
            degraded: false,
        );
    }

    /**
     * Fail-closed: só aprova JSON objeto com a chave `valido` estritamente true.
     * Texto puro, Markdown ou json_decode inválido viram rejeição.
     *
     * @return array{valido: bool, motivo_rejeicao: string|null, prompt_gerado: string}
     */
    public function decodeStructuredResponse(mixed $raw): array
    {
        if (is_array($raw) && isset($raw['_raw']) && is_string($raw['_raw'])) {
            $raw = $raw['_raw'];
        }

        if (is_string($raw)) {
            $decoded = json_decode(trim($raw), true);

            if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
                return $this->rejectedPayload();
            }

            $raw = $decoded;
        }

        if (! is_array($raw) || ! array_key_exists('valido', $raw)) {
            return $this->rejectedPayload();
        }

        return $this->normalizePayload($raw);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{valido: bool, motivo_rejeicao: string|null, prompt_gerado: string}
     */
    public function normalizePayload(array $payload): array
    {
        $valido = true === ($payload['valido'] ?? null);

        $motivo = $payload['motivo_rejeicao'] ?? null;
        $motivo = is_string($motivo) && trim($motivo) !== '' && strtolower(trim($motivo)) !== 'null'
            ? trim($motivo)
            : null;

        $prompt = $payload['prompt_gerado'] ?? '';
        $prompt = is_string($prompt) ? trim($prompt) : '';

        return [
            'valido' => $valido,
            'motivo_rejeicao' => $valido ? null : ($motivo ?? self::UNCLEAR_MESSAGE),
            'prompt_gerado' => $valido ? $prompt : '',
        ];
    }

    /**
     * @return array{valido: bool, motivo_rejeicao: string, prompt_gerado: string}
     */
    private function rejectedPayload(): array
    {
        return [
            'valido' => false,
            'motivo_rejeicao' => self::UNCLEAR_MESSAGE,
            'prompt_gerado' => '',
        ];
    }

    /**
     * Envelopa o corpo gerado nas camadas profissionais. Se a montagem
     * falhar, devolve o corpo original: a geração já foi aprovada.
     *
     * @param  array<string, mixed>  $intent
     */
    private function assembleProfessionalPrompt(string $prompt, array $intent, Template $template, string $rawIntent): string
    {
        try {
            return $this->builder->assemble($prompt, $intent, $template, $rawIntent);
        } catch (PromptAssemblyException $e) {
            $this->logger?->warning($e->getMessage(), [
                'template_id' => $template->getKey(),
            ]);

            return $prompt;
        }
    }

    /**
     * Fail-closed: só o booleano JSON `true` aprova. "true", 1 e qualquer
     * outra forma são dúvida e caem em rejeição.
     */
    public function toBool(mixed $value): bool
    {
        return true === $value;
    }

    /**
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
     * @param  array<string, mixed>  $intent
     * @param  array<string, mixed>  $customVariables
     * @return array<string, string>
     */
    private function variables(array $intent, array $customVariables, string $intencao): array
    {
        $technologies = [];

        foreach ($intent['technologies'] ?? [] as $item) {
            if (is_string($item) && trim($item) !== '') {
                $technologies[] = trim($item);
            }
        }

        $briefing = (new IntentSynthesizer(new NullAIProvider))->frame($intencao, $intent);

        $map = [
            'intencao' => $intencao,
            'user_input' => $briefing,
            'objective' => is_string($intent['objective'] ?? null) ? $intent['objective'] : $intencao,
            'type' => is_string($intent['type'] ?? null) ? $intent['type'] : '',
            'architecture' => is_string($intent['architecture'] ?? null) ? $intent['architecture'] : '',
            'technologies' => implode(', ', $technologies),
            'language' => $technologies[0] ?? '',
            'framework' => $technologies[1] ?? '',
        ];

        foreach ($customVariables as $key => $value) {
            if (! is_string($key) || $key === '' || in_array($key, TemplateInterpolator::RESERVED_VARIABLES, true)) {
                continue;
            }

            if (is_scalar($value) && ! is_bool($value)) {
                $map[$key] = trim((string) $value);
            }
        }

        return $map;
    }
}
