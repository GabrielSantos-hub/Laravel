<?php

namespace Tests\Feature;

use App\Contracts\AIProviderInterface;
use App\Models\Architecture;
use App\Models\Framework;
use App\Models\Language;
use App\Models\Prompt;
use App\Models\Template;
use App\Models\User;
use App\Services\AI\Providers\GeminiAIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A integração vista de fora: driver `gemini` ativo, requisições HTTP falsas e
 * a garantia central desta etapa — API de IA fora do ar não vira erro 500.
 */
class GeminiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.provider' => 'gemini',
            'services.gemini.key' => 'chave-de-teste',
            'services.gemini.model' => 'gemini-2.0-flash',
            // Sem retry: o teste não precisa esperar o backoff.
            'services.gemini.tries' => 1,
        ]);

        Http::preventStrayRequests();
        Cache::flush();

        $this->usuario = User::factory()->create();
    }

    public function test_o_container_resolve_o_gemini_quando_o_driver_esta_configurado(): void
    {
        $this->assertInstanceOf(GeminiAIProvider::class, app(AIProviderInterface::class));
    }

    public function test_geracao_usa_o_gemini_quando_a_api_responde(): void
    {
        $template = $this->templateClassificado();

        Http::fake(['*' => Http::response($this->resposta(json_encode([
            'valido' => true,
            'motivo_rejeicao' => null,
            'prompt_gerado' => 'Prompt final redigido pelo Gemini.',
        ])))]);

        $resposta = $this->actingAs($this->usuario)->postJson(route('prompts.generate'), [
            'user_input' => 'Criar uma API REST de pedidos em Laravel com PHP.',
        ]);

        $resposta->assertCreated();
        $resposta->assertJsonPath('prompt', 'Prompt final redigido pelo Gemini.');
        $resposta->assertJsonPath('template.id', $template->id);
        $resposta->assertJsonPath('degraded', false);
        $resposta->assertJsonPath('intent.objective', 'Criar uma API REST de pedidos em Laravel com PHP');

        Http::assertSentCount(1);

        $this->assertSame(
            'Prompt final redigido pelo Gemini.',
            Prompt::query()->sole()->output_text
        );
    }

    public function test_falha_do_gemini_recusa_por_fail_closed_sem_erro_500(): void
    {
        $this->templateClassificado();

        Http::fake(['*' => Http::response([], 500)]);

        $resposta = $this->actingAs($this->usuario)->postJson(route('prompts.generate'), [
            'user_input' => 'Criar uma API REST em Laravel com PHP seguindo Clean Architecture.',
        ]);

        $resposta->assertUnprocessable();
        $resposta->assertJsonValidationErrorFor('intencao');
        Http::assertSentCount(1);
        $this->assertDatabaseCount('prompts', 0);
    }

    public function test_markdown_fora_de_json_tambem_e_recusado(): void
    {
        $this->templateClassificado();

        Http::fake(['*' => Http::response($this->resposta(
            "```markdown\nAqui está um sistema de banana frita.\n```"
        ))]);

        $resposta = $this->actingAs($this->usuario)->postJson(route('prompts.generate'), [
            'user_input' => 'api rest json banana frita com queijo e cebola roxa rodando em background',
        ]);

        $resposta->assertUnprocessable();
        $resposta->assertJsonValidationErrorFor('intencao');
        $this->assertDatabaseCount('prompts', 0);
    }

    public function test_cota_esgotada_no_gemini_tambem_recusa_em_vez_de_aprovar(): void
    {
        $this->templateClassificado();

        Http::fake(['*' => Http::response([
            'error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED', 'message' => 'Quota exceeded.'],
        ], 429)]);

        $resposta = $this->actingAs($this->usuario)
            ->from(route('home'))
            ->post(route('prompts.generate'), [
                'user_input' => 'Criar uma API REST em Laravel com PHP.',
            ]);

        $resposta->assertRedirect(route('home'));
        $resposta->assertSessionHasErrors('intencao');
        $this->assertDatabaseCount('prompts', 0);
    }

    public function test_chave_ausente_recusa_sem_nenhuma_chamada_de_rede(): void
    {
        $this->templateClassificado();

        config(['services.gemini.key' => null]);

        $resposta = $this->actingAs($this->usuario)->postJson(route('prompts.generate'), [
            'user_input' => 'Criar uma API REST em Laravel com PHP.',
        ]);

        $resposta->assertUnprocessable();
        $resposta->assertJsonValidationErrorFor('intencao');
    }

    public function test_erro_de_dominio_continua_virando_mensagem_de_formulario(): void
    {
        $this->templateClassificado();

        Http::fake(['*' => Http::response([], 500)]);

        $resposta = $this->actingAs($this->usuario)
            ->postJson(route('prompts.generate'), ['user_input' => 'asdfgh qwerty zxcvbn']);

        $resposta->assertUnprocessable();
        $resposta->assertJsonValidationErrorFor('intencao');
        $resposta->assertJsonValidationErrors([
            'intencao' => 'Não conseguimos identificar uma instrução ou objetivo claro de software no seu texto. Por favor, descreva de forma mais detalhada o que você deseja construir.',
        ]);
        $this->assertDatabaseCount('prompts', 0);
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

    private function templateClassificado(): Template
    {
        $php = Language::query()->create(['nome' => 'PHP', 'slug' => 'php']);
        $laravel = Framework::query()->create([
            'nome' => 'Laravel',
            'slug' => 'laravel',
            'language_id' => $php->id,
        ]);
        $clean = Architecture::query()->create([
            'nome' => 'Clean Architecture',
            'descricao' => 'Camadas independentes de framework.',
        ]);

        $template = Template::query()->create([
            'nome' => 'Template Laravel',
            'corpo_template' => 'Especialista em {technologies}, seguindo {architecture}. Tarefa: {user_input}',
            'versao' => '1',
            'is_active' => true,
        ]);

        $template->languages()->attach($php);
        $template->frameworks()->attach($laravel);
        $template->architectures()->attach($clean);

        return $template;
    }
}
