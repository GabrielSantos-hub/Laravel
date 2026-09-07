<?php

namespace App\Services\AI\Providers;

use App\Contracts\AIProviderInterface;
use App\Exceptions\AIProviderException;
use App\Services\AI\IntentAnalyzer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Provedor apoiado na API gratuita do Google Gemini.
 *
 * Duas decisões guiam a classe:
 *
 * A chave vai no cabeçalho `x-goog-api-key`, não na query string. A API aceita
 * as duas formas, mas URL entra em log de acesso, em relatório de exceção e no
 * histórico de proxy — cabeçalho, não.
 *
 * Qualquer falha (rede, cota, chave ausente, resposta sem conteúdo) sai daqui
 * como AIProviderException, sempre com a causa original em `getPrevious()`.
 * Quem decide o que fazer com isso é o PromptPipelineService, que degrada para
 * o provedor offline em vez de devolver erro ao usuário.
 */
class GeminiAIProvider implements AIProviderInterface
{
    public const DEFAULT_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

    public const DEFAULT_MODEL = 'gemini-2.0-flash';

    /** Status que valem nova tentativa: cota momentânea e indisponibilidade. */
    private const RETRYABLE_STATUSES = [429, 500, 502, 503, 504];

    private const ANALYSIS_INSTRUCTION = <<<'TXT'
        Você extrai a intenção técnica de um pedido escrito em linguagem natural por um desenvolvedor.

        Regras:
        - `objective`: uma frase curta, no infinitivo, dizendo o que deve ser feito.
        - `technologies`: linguagens, frameworks, bancos e ferramentas citados ou claramente implícitos. Use o nome canônico (PHP, Laravel, Vue.js, Node.js, PostgreSQL).
        - `architecture`: o estilo arquitetural pedido, se houver. Omita quando não houver menção.
        - `constraints`: restrições explícitas do usuário (proibições, limites, obrigações). Não invente.
        - `type`: a natureza do trabalho.
        - Responda no idioma do pedido e não acrescente nada além dos campos pedidos.
        TXT;

    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly string $model = self::DEFAULT_MODEL,
        private readonly string $baseUrl = self::DEFAULT_BASE_URL,
        private readonly int $timeout = 15,
        private readonly int $tries = 2,
    ) {}

    public function analyzeIntent(string $userInput): array
    {
        $raw = $this->generateContent('analyzeIntent', self::ANALYSIS_INSTRUCTION, $userInput, [
            // Extração de dados quer determinismo, não criatividade.
            'temperature' => 0.1,
            'responseMimeType' => 'application/json',
            'responseSchema' => $this->intentSchema(),
        ]);

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            throw $this->failure('analyzeIntent', 'a resposta não é um JSON válido.');
        }

        // O IntentAnalyzer normaliza chaves ausentes e tipos inesperados, então
        // aqui basta garantir que veio um mapa.
        return $decoded;
    }

    public function composePrompt(string $instruction, string $templateBody, array $variables): string
    {
        return $this->generateContent(
            'composePrompt',
            $instruction,
            $this->composeMessage($templateBody, $variables),
            ['temperature' => 0.4]
        );
    }

    public function name(): string
    {
        return 'gemini';
    }

    /**
     * @param  array<string, mixed>  $generationConfig
     *
     * @throws AIProviderException
     */
    private function generateContent(
        string $operation,
        string $instruction,
        string $userText,
        array $generationConfig
    ): string {
        if (($this->apiKey ?? '') === '') {
            throw $this->failure($operation, 'GEMINI_API_KEY não está configurada.');
        }

        try {
            $response = $this->request()->post("/models/{$this->model}:generateContent", [
                'systemInstruction' => ['parts' => [['text' => $instruction]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $userText]]]],
                'generationConfig' => $generationConfig,
            ]);
        } catch (Throwable $e) {
            // Timeout, DNS, TLS: nada além da rede chegou até aqui.
            throw AIProviderException::during($this->name(), $operation, $e);
        }

        if ($response->failed()) {
            throw $this->failure($operation, $this->describeFailure($response));
        }

        $text = $this->extractText($response->json());

        if ($text === '') {
            throw $this->failure($operation, $this->describeEmptyAnswer($response->json()));
        }

        return $text;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['x-goog-api-key' => $this->apiKey])
            ->acceptJson()
            ->asJson()
            ->connectTimeout(min(5, $this->timeout))
            ->timeout($this->timeout)
            // throw: false devolve a resposta com erro em vez de estourar, para
            // a mensagem final poder citar o status e o motivo da API.
            ->retry(max(1, $this->tries), 250, $this->shouldRetry(), throw: false);
    }

    private function shouldRetry(): callable
    {
        return function (Throwable $e): bool {
            $status = $e instanceof RequestException ? $e->response->status() : null;

            // Sem status é falha de conexão, que vale nova tentativa. Com
            // status, só repete o que é transitório: 401/403/400 não melhoram.
            return $status === null || in_array($status, self::RETRYABLE_STATUSES, true);
        };
    }

    /**
     * O corpo do template e as variáveis vão separados para o modelo não
     * confundir instrução com conteúdo a ser interpolado.
     *
     * @param  array<string, string>  $variables
     */
    private function composeMessage(string $templateBody, array $variables): string
    {
        $json = json_encode($variables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return <<<TXT
            CORPO DO TEMPLATE:
            <<<TEMPLATE
            {$templateBody}
            TEMPLATE

            VARIÁVEIS:
            {$json}
            TXT;
    }

    /**
     * Schema de saída estruturada. Amarrar o formato aqui é mais confiável do
     * que pedir JSON na instrução e torcer pelo resultado.
     *
     * @return array<string, mixed>
     */
    private function intentSchema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'objective' => ['type' => 'STRING'],
                'technologies' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                'architecture' => ['type' => 'STRING'],
                'constraints' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                'type' => ['type' => 'STRING', 'enum' => IntentAnalyzer::TYPES],
            ],
            // `architecture` fica fora: nem todo pedido menciona arquitetura, e
            // exigi-la faria o modelo inventar uma.
            'required' => ['objective', 'technologies', 'constraints', 'type'],
        ];
    }

    private function extractText(mixed $json): string
    {
        $parts = data_get($json, 'candidates.0.content.parts');

        if (! is_array($parts)) {
            return '';
        }

        $text = '';

        foreach ($parts as $part) {
            $piece = $part['text'] ?? null;

            if (is_string($piece)) {
                $text .= $piece;
            }
        }

        return trim($text);
    }

    private function describeFailure(Response $response): string
    {
        $status = $response->status();
        $motivo = data_get($response->json(), 'error.message');
        $codigo = data_get($response->json(), 'error.status');

        $detalhe = collect([$codigo, $motivo])
            ->filter(fn ($valor): bool => is_string($valor) && $valor !== '')
            ->implode(': ');

        return $detalhe === ''
            ? "a API respondeu HTTP {$status}."
            : "a API respondeu HTTP {$status} — ".mb_substr($detalhe, 0, 300);
    }

    /**
     * Resposta 200 sem texto normalmente é bloqueio de segurança ou corte por
     * limite de tokens. Dizer qual dos dois economiza depuração.
     */
    private function describeEmptyAnswer(mixed $json): string
    {
        $bloqueio = data_get($json, 'promptFeedback.blockReason');
        $encerramento = data_get($json, 'candidates.0.finishReason');

        if (is_string($bloqueio) && $bloqueio !== '') {
            return "o pedido foi bloqueado pelos filtros do modelo ({$bloqueio}).";
        }

        if (is_string($encerramento) && $encerramento !== '' && $encerramento !== 'STOP') {
            return "a geração terminou sem conteúdo ({$encerramento}).";
        }

        return 'a resposta não trouxe conteúdo algum.';
    }

    private function failure(string $operation, string $motivo): AIProviderException
    {
        return AIProviderException::during($this->name(), $operation, new RuntimeException($motivo));
    }
}
