<?php

namespace Tests\Unit\Services;

use App\Contracts\AIProviderInterface;
use App\Exceptions\AIProviderException;
use App\Services\AI\IntentAnalyzer;
use App\Services\AI\Providers\GeminiAIProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiAIProviderTest extends TestCase
{
    private const CHAVE = 'chave-secreta-de-teste';

    private const URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';

    protected function setUp(): void
    {
        parent::setUp();

        // Nenhum teste desta classe pode tocar a rede: qualquer requisição não
        // prevista estoura em vez de sair para a internet.
        Http::preventStrayRequests();
    }

    public function test_implementa_o_contrato_e_se_identifica(): void
    {
        $provider = $this->provider();

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
        $this->assertSame('gemini', $provider->name());
    }

    // Análise da intenção

    public function test_analyze_intent_devolve_o_json_estruturado_do_modelo(): void
    {
        Http::fake(['*' => Http::response($this->resposta(json_encode([
            'objective' => 'Criar uma API REST de pedidos',
            'technologies' => ['PHP', 'Laravel'],
            'architecture' => 'Clean Architecture',
            'constraints' => ['Não usar pacotes pagos'],
            'type' => 'feature',
        ])))]);

        $resultado = $this->provider()->analyzeIntent('Criar uma API REST de pedidos em Laravel.');

        $this->assertSame('Criar uma API REST de pedidos', $resultado['objective']);
        $this->assertSame(['PHP', 'Laravel'], $resultado['technologies']);
        $this->assertSame('Clean Architecture', $resultado['architecture']);
        $this->assertSame(['Não usar pacotes pagos'], $resultado['constraints']);
        $this->assertSame('feature', $resultado['type']);
    }

    public function test_a_analise_exige_saida_estruturada_com_schema(): void
    {
        Http::fake(['*' => Http::response($this->resposta('{"objective":"x"}'))]);

        $this->provider()->analyzeIntent('Criar uma API REST em Laravel.');

        Http::assertSent(function (Request $request): bool {
            $config = $request['generationConfig'];

            $this->assertSame(self::URL, $request->url());
            $this->assertSame('application/json', $config['responseMimeType']);
            $this->assertSame('OBJECT', $config['responseSchema']['type']);
            $this->assertSame(
                IntentAnalyzer::TYPES,
                $config['responseSchema']['properties']['type']['enum']
            );
            $this->assertSame(
                'Criar uma API REST em Laravel.',
                $request['contents'][0]['parts'][0]['text']
            );
            $this->assertNotEmpty($request['systemInstruction']['parts'][0]['text']);

            return true;
        });
    }

    public function test_json_invalido_na_analise_vira_excecao_de_provedor(): void
    {
        Http::fake(['*' => Http::response($this->resposta('desculpe, não consegui responder'))]);

        $this->expectException(AIProviderException::class);
        $this->expectExceptionMessage('falhou na operação "analyzeIntent"');

        $this->provider()->analyzeIntent('Criar uma API REST em Laravel.');
    }

    // Composição do prompt

    public function test_compose_prompt_devolve_o_texto_refinado_pelo_modelo(): void
    {
        Http::fake(['*' => Http::response($this->resposta('Prompt final refinado.'))]);

        $resultado = $this->provider()->composePrompt(
            'Funda o template com as variáveis.',
            'Especialista em {technologies}. Tarefa: {user_input}',
            ['technologies' => 'PHP, Laravel', 'user_input' => 'Criar uma API']
        );

        $this->assertSame('Prompt final refinado.', $resultado);

        Http::assertSent(function (Request $request): bool {
            $texto = $request['contents'][0]['parts'][0]['text'];

            $this->assertStringContainsString('Especialista em {technologies}', $texto);
            $this->assertStringContainsString('PHP, Laravel', $texto);
            $this->assertArrayNotHasKey('responseMimeType', $request['generationConfig']);

            return true;
        });
    }

    public function test_partes_multiplas_da_resposta_sao_concatenadas(): void
    {
        Http::fake(['*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => 'Primeira parte. '], ['text' => 'Segunda parte.']]],
                'finishReason' => 'STOP',
            ]],
        ])]);

        $this->assertSame(
            'Primeira parte. Segunda parte.',
            $this->provider()->composePrompt('Instrução', 'Corpo', [])
        );
    }

    // Falhas

    public function test_erro_do_servidor_vira_excecao_com_o_status_e_o_motivo(): void
    {
        Http::fake(['*' => Http::response([
            'error' => ['code' => 500, 'status' => 'INTERNAL', 'message' => 'Internal error encountered.'],
        ], 500)]);

        try {
            $this->provider()->analyzeIntent('Criar uma API REST em Laravel.');
            $this->fail('Esperava uma AIProviderException.');
        } catch (AIProviderException $e) {
            $this->assertStringContainsString('HTTP 500', $e->getPrevious()->getMessage());
            $this->assertStringContainsString('INTERNAL', $e->getPrevious()->getMessage());
        }
    }

    public function test_cota_esgotada_e_repetida_antes_de_falhar(): void
    {
        Http::fake(['*' => Http::response([
            'error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED', 'message' => 'Quota exceeded.'],
        ], 429)]);

        try {
            $this->provider(tries: 2)->analyzeIntent('Criar uma API REST em Laravel.');
            $this->fail('Esperava uma AIProviderException.');
        } catch (AIProviderException $e) {
            $this->assertStringContainsString('RESOURCE_EXHAUSTED', $e->getPrevious()->getMessage());
        }

        Http::assertSentCount(2);
    }

    public function test_chave_invalida_nao_e_repetida(): void
    {
        Http::fake(['*' => Http::response([
            'error' => ['code' => 401, 'status' => 'UNAUTHENTICATED', 'message' => 'API key not valid.'],
        ], 401)]);

        try {
            $this->provider(tries: 3)->analyzeIntent('Criar uma API REST em Laravel.');
            $this->fail('Esperava uma AIProviderException.');
        } catch (AIProviderException) {
            // Repetir erro permanente só queima cota.
        }

        Http::assertSentCount(1);
    }

    public function test_falha_de_conexao_vira_excecao_de_provedor(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        try {
            $this->provider()->analyzeIntent('Criar uma API REST em Laravel.');
            $this->fail('Esperava uma AIProviderException.');
        } catch (AIProviderException $e) {
            $this->assertInstanceOf(ConnectionException::class, $e->getPrevious());
        }
    }

    public function test_resposta_bloqueada_pelos_filtros_vira_excecao(): void
    {
        Http::fake(['*' => Http::response(['promptFeedback' => ['blockReason' => 'SAFETY']])]);

        try {
            $this->provider()->composePrompt('Instrução', 'Corpo', []);
            $this->fail('Esperava uma AIProviderException.');
        } catch (AIProviderException $e) {
            $this->assertStringContainsString('bloqueado', $e->getPrevious()->getMessage());
            $this->assertStringContainsString('SAFETY', $e->getPrevious()->getMessage());
        }
    }

    public function test_geracao_truncada_vira_excecao_citando_o_motivo(): void
    {
        Http::fake(['*' => Http::response([
            'candidates' => [['content' => ['parts' => []], 'finishReason' => 'MAX_TOKENS']],
        ])]);

        try {
            $this->provider()->composePrompt('Instrução', 'Corpo', []);
            $this->fail('Esperava uma AIProviderException.');
        } catch (AIProviderException $e) {
            $this->assertStringContainsString('MAX_TOKENS', $e->getPrevious()->getMessage());
        }
    }

    // Segurança

    public function test_sem_chave_configurada_nao_ha_chamada_http(): void
    {
        // Sem Http::fake: se houvesse requisição, preventStrayRequests estouraria.
        try {
            $this->provider(apiKey: null)->analyzeIntent('Criar uma API REST em Laravel.');
            $this->fail('Esperava uma AIProviderException.');
        } catch (AIProviderException $e) {
            $this->assertStringContainsString('GEMINI_API_KEY', $e->getPrevious()->getMessage());
        }
    }

    public function test_chave_em_branco_e_tratada_como_ausente(): void
    {
        $this->expectException(AIProviderException::class);

        $this->provider(apiKey: '')->composePrompt('Instrução', 'Corpo', []);
    }

    public function test_a_chave_vai_no_cabecalho_e_nunca_na_url(): void
    {
        Http::fake(['*' => Http::response($this->resposta('ok'))]);

        $this->provider()->composePrompt('Instrução', 'Corpo', []);

        Http::assertSent(function (Request $request): bool {
            $this->assertTrue($request->hasHeader('x-goog-api-key', self::CHAVE));
            $this->assertStringNotContainsString(self::CHAVE, $request->url());

            return true;
        });
    }

    public function test_a_mensagem_de_erro_nao_vaza_a_chave_de_api(): void
    {
        Http::fake(['*' => Http::response(['error' => ['status' => 'INTERNAL', 'message' => 'boom']], 500)]);

        try {
            $this->provider()->analyzeIntent('Criar uma API REST em Laravel.');
            $this->fail('Esperava uma AIProviderException.');
        } catch (AIProviderException $e) {
            $this->assertStringNotContainsString(self::CHAVE, $e->getMessage());
            $this->assertStringNotContainsString(self::CHAVE, (string) $e->getPrevious()?->getMessage());
        }
    }

    private function provider(int $tries = 1, ?string $apiKey = self::CHAVE): GeminiAIProvider
    {
        return new GeminiAIProvider(
            apiKey: $apiKey,
            model: 'gemini-2.0-flash',
            baseUrl: GeminiAIProvider::DEFAULT_BASE_URL,
            timeout: 5,
            tries: $tries,
        );
    }

    /**
     * Envelope de resposta da API do Gemini com um único candidato.
     *
     * @return array<string, mixed>
     */
    private function resposta(string $texto): array
    {
        return [
            'candidates' => [[
                'content' => ['role' => 'model', 'parts' => [['text' => $texto]]],
                'finishReason' => 'STOP',
            ]],
        ];
    }
}
