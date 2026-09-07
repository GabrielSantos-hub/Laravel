<?php

namespace Tests\Unit\Services;

use App\Contracts\AIProviderInterface;
use App\Exceptions\InvalidIntentException;
use App\Exceptions\NoCompatibleTemplateException;
use App\Models\Architecture;
use App\Models\Framework;
use App\Models\Language;
use App\Models\Template;
use App\Services\AI\IntentAnalyzer;
use App\Services\AI\NullAIProvider;
use App\Services\AI\PromptComposer;
use App\Services\AI\TemplateSelector;
use App\Services\PromptPipelineResult;
use App\Services\PromptPipelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Stringable;
use Tests\TestCase;

class PromptPipelineServiceTest extends TestCase
{
    use RefreshDatabase;

    private PromptPipelineService $pipeline;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pipeline = app(PromptPipelineService::class);
    }

    public function test_encadeia_as_tres_etapas_e_devolve_o_resultado_completo(): void
    {
        $template = $this->templateClassificado();

        $resultado = $this->pipeline->generate(
            'Criar uma API REST em Laravel com PHP seguindo Clean Architecture.'
        );

        $this->assertInstanceOf(PromptPipelineResult::class, $resultado);
        $this->assertTrue($template->is($resultado->template));
        $this->assertFalse($resultado->manualSelection);

        $this->assertSame(['PHP', 'Laravel'], $resultado->intent['technologies']);
        $this->assertSame('Clean Architecture', $resultado->intent['architecture']);
        $this->assertSame('feature', $resultado->intent['type']);

        $this->assertSame(
            'Especialista em PHP, Laravel, seguindo Clean Architecture. '
            .'Tarefa: Criar uma API REST em Laravel com PHP seguindo Clean Architecture',
            $resultado->prompt
        );
    }

    public function test_o_id_forcado_ignora_a_pontuacao_e_marca_a_selecao_como_manual(): void
    {
        $this->templateClassificado();

        $manual = Template::query()->create([
            'nome' => 'Template manual',
            'corpo_template' => 'Manual: {user_input}',
            'versao' => '1',
            'is_active' => true,
        ]);

        $resultado = $this->pipeline->generate(
            'Criar uma API REST em Laravel com PHP seguindo Clean Architecture.',
            $manual->id
        );

        $this->assertTrue($manual->is($resultado->template));
        $this->assertTrue($resultado->manualSelection);
        $this->assertStringStartsWith('Manual:', $resultado->prompt);
    }

    public function test_sem_template_compativel_lanca_excecao_de_dominio(): void
    {
        $this->templateClassificado();

        try {
            $this->pipeline->generate('asdfgh qwerty zxcvbn');
            $this->fail('Esperava uma NoCompatibleTemplateException.');
        } catch (NoCompatibleTemplateException $e) {
            $this->assertFalse($e->wasManualSelection());
            $this->assertStringContainsString('Nenhum template compatível', $e->getMessage());
        }
    }

    public function test_id_forcado_inativo_lanca_excecao_apontando_a_selecao_manual(): void
    {
        $inativo = Template::query()->create([
            'nome' => 'Template arquivado',
            'corpo_template' => 'Corpo.',
            'versao' => '1',
            'is_active' => false,
        ]);

        try {
            $this->pipeline->generate('Criar uma API REST em Laravel.', $inativo->id);
            $this->fail('Esperava uma NoCompatibleTemplateException.');
        } catch (NoCompatibleTemplateException $e) {
            $this->assertTrue($e->wasManualSelection());
            $this->assertStringContainsString((string) $inativo->id, $e->getMessage());
        }
    }

    public function test_entrada_curta_demais_para_no_analisador(): void
    {
        $this->templateClassificado();

        $this->expectException(InvalidIntentException::class);

        $this->pipeline->generate('oi');
    }

    // Resiliência: provedor de IA indisponível

    public function test_falha_do_provedor_de_ia_degrada_para_o_modo_offline(): void
    {
        $template = $this->templateClassificado();
        $provedor = $this->provedorForaDoAr();

        $resultado = $this->pipelineCom($provedor)->generate(
            'Criar uma API REST em Laravel com PHP seguindo Clean Architecture.'
        );

        // Mesmo resultado que o modo offline produziria, sem exceção alguma.
        $this->assertTrue($template->is($resultado->template));
        $this->assertTrue($resultado->degraded);
        $this->assertSame(['PHP', 'Laravel'], $resultado->intent['technologies']);
        $this->assertSame(
            'Especialista em PHP, Laravel, seguindo Clean Architecture. '
            .'Tarefa: Criar uma API REST em Laravel com PHP seguindo Clean Architecture',
            $resultado->prompt
        );
    }

    public function test_provedor_fora_do_ar_nao_e_chamado_de_novo_na_composicao(): void
    {
        $this->templateClassificado();
        $provedor = $this->provedorForaDoAr();

        $this->pipelineCom($provedor)->generate('Criar uma API REST em Laravel com PHP.');

        $this->assertSame(1, $provedor->analises, 'A análise deveria ter sido tentada uma vez.');
        $this->assertSame(0, $provedor->composicoes, 'A composição não deveria insistir num provedor caído.');
    }

    public function test_a_degradacao_registra_warning_no_log(): void
    {
        $this->templateClassificado();
        $logger = $this->loggerEspiao();

        $this->pipelineCom($this->provedorForaDoAr(), $logger)
            ->generate('Criar uma API REST em Laravel com PHP.');

        $this->assertCount(1, $logger->registros);
        $this->assertSame('warning', $logger->registros[0]['nivel']);
        $this->assertStringContainsString('provedor offline', $logger->registros[0]['mensagem']);
        $this->assertStringContainsString('503', $logger->registros[0]['contexto']['cause']);
    }

    public function test_a_execucao_saudavel_nao_marca_o_resultado_como_degradado(): void
    {
        $this->templateClassificado();
        $logger = $this->loggerEspiao();

        $resultado = $this->pipelineCom(new NullAIProvider, $logger)
            ->generate('Criar uma API REST em Laravel com PHP.');

        $this->assertFalse($resultado->degraded);
        $this->assertSame([], $logger->registros);
    }

    public function test_entrada_invalida_nao_e_degradada_e_continua_subindo(): void
    {
        $this->templateClassificado();

        $this->expectException(InvalidIntentException::class);

        // A validação acontece antes de qualquer chamada ao provedor, então o
        // fallback não pode transformar erro do usuário em prompt gerado.
        $this->pipelineCom($this->provedorForaDoAr())->generate('oi');
    }

    private function pipelineCom(
        AIProviderInterface $provedor,
        ?LoggerInterface $logger = null
    ): PromptPipelineService {
        return new PromptPipelineService(
            new IntentAnalyzer($provedor),
            app(TemplateSelector::class),
            new PromptComposer($provedor),
            $logger,
        );
    }

    /**
     * Provedor que sempre falha e conta quantas vezes foi procurado.
     */
    private function provedorForaDoAr(): AIProviderInterface
    {
        return new class implements AIProviderInterface
        {
            public int $analises = 0;

            public int $composicoes = 0;

            public function analyzeIntent(string $userInput): array
            {
                $this->analises++;

                throw new RuntimeException('503 Service Unavailable');
            }

            public function composePrompt(string $instruction, string $templateBody, array $variables): string
            {
                $this->composicoes++;

                throw new RuntimeException('503 Service Unavailable');
            }

            public function name(): string
            {
                return 'fake-llm';
            }
        };
    }

    private function loggerEspiao(): LoggerInterface
    {
        return new class extends AbstractLogger
        {
            /** @var array<int, array{nivel: string, mensagem: string, contexto: array<string, mixed>}> */
            public array $registros = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->registros[] = [
                    'nivel' => (string) $level,
                    'mensagem' => (string) $message,
                    'contexto' => $context,
                ];
            }
        };
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
