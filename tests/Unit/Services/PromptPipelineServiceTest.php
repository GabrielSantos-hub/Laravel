<?php

namespace Tests\Unit\Services;

use App\Contracts\AIProviderInterface;
use App\Exceptions\InvalidIntentException;
use App\Exceptions\NoCompatibleTemplateException;
use App\Models\Architecture;
use App\Models\Framework;
use App\Models\Language;
use App\Models\Template;
use App\Services\AI\NullAIProvider;
use App\Services\AI\PromptComposer;
use App\Services\AI\TemplateSelector;
use App\Services\PromptGeneratorService;
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

        $this->assertSame(['PHP', 'Laravel'], $resultado->intent['technologies']);
        $this->assertSame('Clean Architecture', $resultado->intent['architecture']);
        $this->assertSame('feature', $resultado->intent['type']);

        $this->assertStringContainsString('Especialista em PHP, Laravel, seguindo Clean Architecture.', $resultado->prompt);
        $this->assertStringContainsString('Regra de negócio', $resultado->prompt);
        $this->assertStringContainsString('Requisitos implícitos', $resultado->prompt);
        $this->assertStringContainsString('Fluxo do usuário', $resultado->prompt);
        $this->assertStringContainsString('fluxo de dados', $resultado->prompt);
        $this->assertDoesNotMatchRegularExpression('/Solicitação do usuário:/iu', $resultado->prompt);
        $this->assertStringNotContainsString(
            'Criar uma API REST em Laravel com PHP seguindo Clean Architecture.',
            $resultado->prompt
        );
    }

    public function test_sem_template_compativel_lanca_excecao_de_dominio(): void
    {
        $this->templateClassificado();

        $this->expectException(NoCompatibleTemplateException::class);
        $this->expectExceptionMessage('Nenhum template compatível');

        $this->pipeline->generate('Criar uma API REST em Django com Python.');
    }

    public function test_template_inativo_fica_de_fora_da_selecao(): void
    {
        Template::query()->create([
            'nome' => 'Template arquivado',
            'corpo_template' => 'Corpo.',
            'versao' => '1',
            'is_active' => false,
        ]);

        $this->expectException(NoCompatibleTemplateException::class);

        $this->pipeline->generate('Criar uma API REST em Laravel.');
    }

    public function test_entrada_curta_demais_para_no_analisador(): void
    {
        $this->templateClassificado();

        $this->expectException(InvalidIntentException::class);

        $this->pipeline->generate('oi');
    }

    // Resiliência: provedor de IA indisponível

    public function test_falha_do_provedor_de_ia_recusa_por_fail_closed(): void
    {
        $this->templateClassificado();

        $this->expectException(InvalidIntentException::class);
        $this->expectExceptionMessage('objetivo claro de software');

        $this->pipelineCom($this->provedorForaDoAr())->generate(
            'Criar uma API REST em Laravel com PHP seguindo Clean Architecture.'
        );
    }

    public function test_provedor_fora_do_ar_nao_e_chamado_de_novo_na_composicao(): void
    {
        $this->templateClassificado();
        $provedor = $this->provedorForaDoAr();

        try {
            $this->pipelineCom($provedor)->generate('Criar uma API REST em Laravel com PHP.');
            $this->fail('Esperava InvalidIntentException.');
        } catch (InvalidIntentException) {
            // fail-closed: sem JSON válido a intenção é recusada.
        }

        $this->assertSame(1, $provedor->estruturados, 'A geração estruturada deveria ter sido tentada uma vez.');
        $this->assertSame(0, $provedor->composicoes, 'A composição não deveria insistir num provedor caído.');
    }

    public function test_a_falha_do_provedor_registra_warning_no_log(): void
    {
        $this->templateClassificado();
        $logger = $this->loggerEspiao();

        try {
            $this->pipelineCom($this->provedorForaDoAr(), $logger)
                ->generate('Criar uma API REST em Laravel com PHP.');
        } catch (InvalidIntentException) {
            // esperado
        }

        $niveis = array_column($logger->registros, 'nivel');
        $this->assertContains('warning', $niveis);
        $aviso = collect($logger->registros)->firstWhere('nivel', 'warning');
        $this->assertIsArray($aviso);
        $this->assertStringContainsString('fail-closed', $aviso['mensagem']);
        $this->assertStringContainsString('503', $aviso['contexto']['cause']);
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

    public function test_pedido_generico_usa_o_template_de_fallback(): void
    {
        $this->templateClassificado();

        $fallback = Template::query()->create([
            'nome' => 'Desenvolvimento de Módulo / Feature',
            'corpo_template' => 'Módulo: {user_input}',
            'versao' => '1',
            'is_active' => true,
        ]);

        $resultado = $this->pipeline->generate('Faça um crud de cadastro de clientes.');

        $this->assertTrue($fallback->is($resultado->template));
        $this->assertStringStartsWith('Módulo:', $resultado->prompt);
    }

    public function test_dicas_do_catalogo_completam_a_intencao_sem_sobrescrever_o_texto(): void
    {
        $template = $this->templateClassificado();
        $php = Language::query()->firstOrFail();
        $laravel = Framework::query()->firstOrFail();
        $clean = Architecture::query()->where('nome', 'Clean Architecture')->firstOrFail();

        $resultado = $this->pipeline->generate(
            'Faça um sistema de login com recuperação de senha.',
            catalogHints: [
                'language_id' => $php->id,
                'framework_id' => $laravel->id,
                'architecture_id' => $clean->id,
            ],
        );

        $this->assertTrue($template->is($resultado->template));
        $this->assertSame(['PHP', 'Laravel'], $resultado->intent['technologies']);
        $this->assertSame('Clean Architecture', $resultado->intent['architecture']);
    }

    public function test_dicas_do_catalogo_nao_sobrescrevem_arquitetura_extraida_do_texto(): void
    {
        $this->templateClassificado();

        $mvc = Architecture::query()->create(['nome' => 'MVC', 'descricao' => 'Camadas.']);

        $resultado = $this->pipeline->generate(
            'Criar uma API REST em Laravel com PHP seguindo Clean Architecture.',
            catalogHints: ['architecture_id' => $mvc->id],
        );

        $this->assertSame('Clean Architecture', $resultado->intent['architecture']);
    }

    public function test_entrada_invalida_nao_e_degradada_e_continua_subindo(): void
    {
        $this->templateClassificado();

        $this->expectException(InvalidIntentException::class);

        // A validação acontece antes de qualquer chamada ao provedor, então o
        // fallback não pode transformar erro do usuário em prompt gerado.
        $this->pipelineCom($this->provedorForaDoAr())->generate('oi');
    }

    public function test_modulo_jwt_nao_cai_no_roleplay(): void
    {
        $php = Language::query()->create(['nome' => 'PHP', 'slug' => 'php']);
        $laravel = Framework::query()->create([
            'nome' => 'Laravel',
            'slug' => 'laravel',
            'language_id' => $php->id,
        ]);

        $roleplay = Template::query()->create([
            'nome' => 'Role-Play & Restrição Absoluta (A1)',
            'slug' => 'roleplay-restricao-absoluta',
            'descricao' => 'Geração direta de código com persona sênior.',
            'intent_type' => 'feature',
            'corpo_template' => 'Roleplay: {user_input}',
            'versao' => '1',
            'is_active' => true,
        ]);
        $roleplay->languages()->attach($php);
        $roleplay->frameworks()->attach($laravel);

        $feature = Template::query()->create([
            'nome' => 'ICCE — Desenvolvimento de Módulo',
            'slug' => 'icce-framework',
            'descricao' => 'Para funcionalidades específicas e desenvolvimento de módulos.',
            'intent_type' => 'feature',
            'corpo_template' => 'Feature: {user_input}',
            'versao' => '1',
            'is_active' => true,
        ]);

        $resultado = $this->pipeline->generate('Criar módulo de autenticação JWT');

        $this->assertTrue($feature->is($resultado->template));
        $this->assertFalse($roleplay->is($resultado->template));
        $this->assertSame('feature', $resultado->intent['type']);
    }

    public function test_arquitetura_microservicos_escolhe_template_de_design(): void
    {
        Template::query()->create([
            'nome' => 'Role-Play & Restrição Absoluta (A1)',
            'slug' => 'roleplay-restricao-absoluta',
            'intent_type' => 'feature',
            'corpo_template' => 'Roleplay: {user_input}',
            'versao' => '1',
            'is_active' => true,
        ]);

        $c4 = Template::query()->create([
            'nome' => 'C4 Model & System Design (D1)',
            'slug' => 'c4-model-system-design',
            'descricao' => 'Desenha a arquitetura em alto nível antes do código.',
            'intent_type' => 'architecture',
            'corpo_template' => 'Arquitetura: {user_input}',
            'versao' => '1',
            'is_active' => true,
        ]);

        $resultado = $this->pipeline->generate('Desenhar arquitetura microserviços');

        $this->assertTrue($c4->is($resultado->template));
        $this->assertSame('architecture', $resultado->intent['type']);
    }

    public function test_mistura_ilogica_com_termos_tecnicos_nao_e_gerada(): void
    {
        $this->templateClassificado();

        $this->expectException(InvalidIntentException::class);
        $this->expectExceptionMessage('objetivo claro de software');

        $this->pipelineCom($this->provedorQueRejeita())->generate('rato motorista analogico sistema mysql');
    }

    private function pipelineCom(
        AIProviderInterface $provedor,
        ?LoggerInterface $logger = null
    ): PromptPipelineService {
        return new PromptPipelineService(
            new PromptGeneratorService(
                $provedor,
                app(TemplateSelector::class),
                new PromptComposer($provedor, logger: $logger),
                $logger,
            )
        );
    }

    /**
     * Provedor que sempre falha e conta quantas vezes foi procurado.
     */
    private function provedorForaDoAr(): AIProviderInterface
    {
        return new class implements AIProviderInterface
        {
            public int $estruturados = 0;

            public int $composicoes = 0;

            public function analyzeIntent(string $userInput): array
            {
                throw new RuntimeException('503 Service Unavailable');
            }

            public function composePrompt(string $instruction, string $templateBody, array $variables): string
            {
                $this->composicoes++;

                throw new RuntimeException('503 Service Unavailable');
            }

            public function generateStructuredPrompt(string $intencao, string $templateBody, array $variables): array
            {
                $this->estruturados++;

                throw new RuntimeException('503 Service Unavailable');
            }

            public function name(): string
            {
                return 'fake-llm';
            }
        };
    }

    private function provedorQueRejeita(): AIProviderInterface
    {
        return new class implements AIProviderInterface
        {
            public function analyzeIntent(string $userInput): array
            {
                return [];
            }

            public function composePrompt(string $instruction, string $templateBody, array $variables): string
            {
                return '';
            }

            public function generateStructuredPrompt(string $intencao, string $templateBody, array $variables): array
            {
                return [
                    'valido' => false,
                    'motivo_rejeicao' => PromptGeneratorService::UNCLEAR_MESSAGE,
                    'prompt_gerado' => '',
                ];
            }

            public function name(): string
            {
                return 'fake-reject';
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
