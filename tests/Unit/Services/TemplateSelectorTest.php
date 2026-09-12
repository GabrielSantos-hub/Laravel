<?php

namespace Tests\Unit\Services;

use App\Models\Architecture;
use App\Models\Framework;
use App\Models\Language;
use App\Models\Template;
use App\Services\AI\TemplateSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateSelectorTest extends TestCase
{
    use RefreshDatabase;

    private TemplateSelector $selector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->selector = new TemplateSelector;
    }

    // (a) Seleção automática

    public function test_template_inativo_fica_fora_da_selecao(): void
    {
        $php = $this->language('PHP', 'php');
        $inativo = $this->template('Template arquivado', active: false);
        $inativo->languages()->attach($php);

        $this->assertNull($this->selector->select($this->intent(technologies: ['PHP'])));
    }

    public function test_selecao_automatica_escolhe_o_template_mais_compativel(): void
    {
        [$php, $python] = [$this->language('PHP', 'php'), $this->language('Python', 'python')];
        $laravel = $this->framework('Laravel', 'laravel', $php);
        $django = $this->framework('Django', 'django', $python);
        $clean = $this->architecture('Clean Architecture');
        $mvc = $this->architecture('MVC');

        $laravelClean = $this->template('Template Laravel');
        $laravelClean->languages()->attach($php);
        $laravelClean->frameworks()->attach($laravel);
        $laravelClean->architectures()->attach($clean);

        $djangoMvc = $this->template('Template Django');
        $djangoMvc->languages()->attach($python);
        $djangoMvc->frameworks()->attach($django);
        $djangoMvc->architectures()->attach($mvc);

        $phpGenerico = $this->template('Template PHP');
        $phpGenerico->languages()->attach($php);

        $selecionado = $this->selector->select($this->intent(
            technologies: ['PHP', 'Laravel'],
            architecture: 'Clean Architecture',
        ));

        $this->assertTrue($laravelClean->is($selecionado));
    }

    public function test_template_sem_classificacao_em_uma_dimensao_nao_e_penalizado(): void
    {
        $php = $this->language('PHP', 'php');
        $this->framework('Laravel', 'laravel', $php);

        $phpGenerico = $this->template('Template PHP');
        $phpGenerico->languages()->attach($php);

        $selecionado = $this->selector->select($this->intent(
            technologies: ['PHP', 'Laravel'],
            architecture: 'Clean Architecture',
        ));

        $this->assertTrue($phpGenerico->is($selecionado));
    }

    public function test_normalizacao_casa_nome_slug_e_acentuacao(): void
    {
        $js = $this->language('JavaScript', 'javascript');
        $vue = $this->framework('Vue.js', 'vue-js', $js);
        $limpa = $this->architecture('Arquitetura Limpa');

        $template = $this->template('Template Vue');
        $template->frameworks()->attach($vue);
        $template->architectures()->attach($limpa);

        $selecionado = $this->selector->select($this->intent(
            technologies: ['vue-js'],
            architecture: 'arquitetura limpa',
        ));

        $this->assertTrue($template->is($selecionado));
    }

    public function test_fallback_textual_atende_templates_ainda_nao_classificados(): void
    {
        $this->language('PHP', 'php');

        $generico = $this->template('Genérico', 'Instruções gerais de escrita de código.');
        $comMencao = $this->template('Especialista', 'Você é um especialista em Laravel e PHP.');

        $selecionado = $this->selector->select($this->intent(technologies: ['PHP', 'Laravel']));

        $this->assertTrue($comMencao->is($selecionado));
        $this->assertFalse($generico->is($selecionado));
    }

    public function test_template_classificado_vence_template_apenas_textual(): void
    {
        $php = $this->language('PHP', 'php');

        $textual = $this->template('Textual', 'Especialista em PHP, Laravel e Clean Architecture.');

        $classificado = $this->template('Classificado');
        $classificado->languages()->attach($php);

        $selecionado = $this->selector->select($this->intent(
            technologies: ['PHP', 'Laravel'],
            architecture: 'Clean Architecture',
        ));

        $this->assertTrue($classificado->is($selecionado));
    }

    public function test_o_tipo_da_intencao_desempata_pelo_nome_do_template(): void
    {
        $php = $this->language('PHP', 'php');

        $neutro = $this->template('Template PHP');
        $neutro->languages()->attach($php);

        $correcao = $this->template('Correção de Bug');
        $correcao->languages()->attach($php);

        $selecionado = $this->selector->select($this->intent(
            technologies: ['PHP'],
            type: 'bugfix',
        ));

        $this->assertTrue($correcao->is($selecionado));
    }

    public function test_templates_inativos_sao_ignorados_na_selecao_automatica(): void
    {
        $php = $this->language('PHP', 'php');
        $laravel = $this->framework('Laravel', 'laravel', $php);

        $inativoPerfeito = $this->template('Template Laravel', active: false);
        $inativoPerfeito->languages()->attach($php);
        $inativoPerfeito->frameworks()->attach($laravel);

        $ativoFraco = $this->template('Template PHP');
        $ativoFraco->languages()->attach($php);

        $selecionado = $this->selector->select($this->intent(technologies: ['PHP', 'Laravel']));

        $this->assertTrue($ativoFraco->is($selecionado));
    }

    public function test_empate_e_resolvido_pelo_menor_id(): void
    {
        $php = $this->language('PHP', 'php');

        $primeiro = $this->template('Template A');
        $primeiro->languages()->attach($php);

        $segundo = $this->template('Template B');
        $segundo->languages()->attach($php);

        $selecionado = $this->selector->select($this->intent(technologies: ['PHP']));

        $this->assertTrue($primeiro->is($selecionado));
    }

    // (c) Nenhum template compatível

    public function test_retorna_null_quando_a_stack_da_intencao_conflita_com_o_catalogo(): void
    {
        $php = $this->language('PHP', 'php');
        $python = $this->language('Python', 'python');
        $django = $this->framework('Django', 'django', $python);

        $somentePython = $this->template('Template Django');
        $somentePython->languages()->attach($python);
        $somentePython->frameworks()->attach($django);

        $selecionado = $this->selector->select($this->intent(technologies: ['PHP']));

        $this->assertNull($selecionado);
    }

    public function test_retorna_null_quando_nao_existe_nenhum_template(): void
    {
        $this->language('PHP', 'php');

        $this->assertNull($this->selector->select($this->intent(technologies: ['PHP'])));
    }

    public function test_retorna_null_quando_a_intencao_nao_traz_sinal_algum(): void
    {
        $php = $this->language('PHP', 'php');

        $template = $this->template('Template PHP');
        $template->languages()->attach($php);

        $this->assertNull($this->selector->select($this->intent()));
    }

    public function test_intencao_malformada_nao_quebra_a_selecao(): void
    {
        $generico = $this->template('Desenvolvimento de Módulo / Feature', 'Corpo genérico.');

        $this->assertTrue($generico->is($this->selector->select([])));
        $this->assertTrue($generico->is($this->selector->select([
            'technologies' => 'PHP',
            'architecture' => ['inválido'],
            'type' => null,
        ])));
    }

    public function test_pedido_generico_cai_no_template_de_fallback(): void
    {
        $php = $this->language('PHP', 'php');

        $classificado = $this->template('Template Laravel');
        $classificado->languages()->attach($php);

        $documentacao = $this->template('Documentação Técnica');
        $fallback = $this->template('Desenvolvimento de Módulo / Feature');

        $selecionado = $this->selector->select($this->intent(type: 'feature'));

        $this->assertTrue($fallback->is($selecionado));
        $this->assertFalse($classificado->is($selecionado));
        $this->assertFalse($documentacao->is($selecionado));
    }

    public function test_fallback_nao_escolhe_template_classificado_incompativel(): void
    {
        $python = $this->language('Python', 'python');
        $somentePython = $this->template('Template Django');
        $somentePython->languages()->attach($python);

        $fallback = $this->template('Prompt Genérico');

        $selecionado = $this->selector->select($this->intent(technologies: ['PHP']));

        $this->assertTrue($fallback->is($selecionado));
        $this->assertFalse($somentePython->is($selecionado));
    }

    // Helpers

    /**
     * @param  array<int, string>  $technologies
     * @return array<string, mixed>
     */
    public function test_modulo_jwt_escolhe_template_de_features_e_nao_roleplay(): void
    {
        $php = $this->language('PHP', 'php');
        $laravel = $this->framework('Laravel', 'laravel', $php);

        $roleplay = $this->template(
            'Role-Play & Restrição Absoluta (A1)',
            extra: [
                'slug' => 'roleplay-restricao-absoluta',
                'intent_type' => 'feature',
                'descricao' => 'Geração direta de código com persona sênior.',
            ],
        );
        $roleplay->languages()->attach($php);
        $roleplay->frameworks()->attach($laravel);

        $feature = $this->template(
            'ICCE — Instrução, Contexto, Restrição, Exemplo (A2)',
            extra: [
                'slug' => 'icce-framework',
                'intent_type' => 'feature',
                'descricao' => 'Para funcionalidades específicas e desenvolvimento de módulos.',
            ],
        );

        $selecionado = $this->selector->select($this->intent(
            technologies: ['PHP', 'Laravel'],
            type: 'feature',
            objective: 'Criar módulo de autenticação JWT',
        ));

        $this->assertTrue($feature->is($selecionado));
        $this->assertFalse($roleplay->is($selecionado));
    }

    public function test_arquitetura_microservicos_escolhe_template_de_design(): void
    {
        $this->template(
            'Role-Play & Restrição Absoluta (A1)',
            extra: [
                'slug' => 'roleplay-restricao-absoluta',
                'intent_type' => 'feature',
                'descricao' => 'Geração direta de código com persona sênior.',
            ],
        );

        $c4 = $this->template(
            'C4 Model & System Design (D1)',
            extra: [
                'slug' => 'c4-model-system-design',
                'intent_type' => 'architecture',
                'descricao' => 'Desenha a arquitetura em alto nível antes do código.',
            ],
        );

        $this->template(
            'Desenvolvimento Geral & Assistente de Prompt (Fallback)',
            extra: [
                'slug' => 'desenvolvimento-geral-fallback',
                'intent_type' => 'generic',
                'is_generic' => true,
            ],
        );

        $selecionado = $this->selector->select($this->intent(
            architecture: 'Microservices',
            type: 'architecture',
            objective: 'Desenhar arquitetura microserviços',
        ));

        $this->assertTrue($c4->is($selecionado));
    }

    public function test_falha_na_selecao_via_ia_e_registrada_em_log(): void
    {
        $feature = $this->template(
            'ICCE — Funcionalidades',
            extra: ['intent_type' => 'feature', 'descricao' => 'Desenvolvimento de módulos.'],
        );

        $provider = new class implements \App\Contracts\AIProviderInterface
        {
            public function analyzeIntent(string $userInput): array
            {
                return [];
            }

            public function composePrompt(string $instruction, string $templateBody, array $variables): string
            {
                throw new \RuntimeException('Falha de API ao classificar template');
            }

            public function generateStructuredPrompt(string $intencao, string $templateBody, array $variables): array
            {
                throw new \RuntimeException('Falha de API ao classificar template');
            }

            public function name(): string
            {
                return 'gemini';
            }
        };

        $logger = new class extends \Psr\Log\AbstractLogger
        {
            /** @var list<array{level: string, message: string}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => (string) $level, 'message' => (string) $message];
            }
        };

        $selecionado = (new TemplateSelector($provider, $logger))->select($this->intent(
            type: 'feature',
            objective: 'Criar módulo de autenticação JWT',
        ));

        $this->assertTrue($feature->is($selecionado));
        $this->assertSame('error', $logger->records[0]['level'] ?? null);
        $this->assertSame('Falha de API ao classificar template', $logger->records[0]['message'] ?? null);
    }

    public function test_ia_recebe_catalogo_com_ids_e_nomes_antes_de_escolher(): void
    {
        $feature = $this->template(
            'Desenvolvimento de Módulo / Feature',
            extra: ['intent_type' => 'feature', 'descricao' => 'Features e funcionalidades.'],
        );
        $roleplay = $this->template(
            'Role-Play & Restrição Absoluta (A1)',
            extra: ['intent_type' => 'feature', 'slug' => 'roleplay-restricao-absoluta'],
        );

        $provider = new class implements \App\Contracts\AIProviderInterface
        {
            public string $instruction = '';

            public function analyzeIntent(string $userInput): array
            {
                return [];
            }

            public function composePrompt(string $instruction, string $templateBody, array $variables): string
            {
                $this->instruction = $instruction;

                return (string) ($variables['catalog'] ?? '');
            }

            public function generateStructuredPrompt(string $intencao, string $templateBody, array $variables): array
            {
                return [
                    'valido' => true,
                    'motivo_rejeicao' => null,
                    'prompt_gerado' => $this->composePrompt('instrução', $templateBody, $variables),
                ];
            }

            public function name(): string
            {
                return 'gemini';
            }
        };

        $selecionado = (new TemplateSelector($provider))->select($this->intent(
            type: 'feature',
            objective: 'Criar módulo de autenticação JWT',
        ));

        $this->assertStringContainsString('ID '.$feature->id, $provider->instruction);
        $this->assertStringContainsString('ID '.$roleplay->id, $provider->instruction);
        $this->assertStringContainsString('Desenvolvimento de Módulo / Feature', $provider->instruction);
        $this->assertTrue($feature->is($selecionado) || $roleplay->is($selecionado));
    }

    private function intent(
        array $technologies = [],
        ?string $architecture = null,
        string $type = 'general',
        string $objective = 'Objetivo de teste',
    ): array {
        return [
            'objective' => $objective,
            'technologies' => $technologies,
            'architecture' => $architecture,
            'constraints' => [],
            'type' => $type,
        ];
    }

    private function language(string $nome, string $slug): Language
    {
        return Language::query()->create(['nome' => $nome, 'slug' => $slug]);
    }

    private function framework(string $nome, string $slug, Language $language): Framework
    {
        return Framework::query()->create([
            'nome' => $nome,
            'slug' => $slug,
            'language_id' => $language->id,
        ]);
    }

    private function architecture(string $nome): Architecture
    {
        return Architecture::query()->create([
            'nome' => $nome,
            'descricao' => "Descrição de {$nome}",
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function template(string $nome, string $corpo = 'Corpo do template.', bool $active = true, array $extra = []): Template
    {
        return Template::query()->create([
            'nome' => $nome,
            'corpo_template' => $corpo,
            'versao' => '1',
            'is_active' => $active,
            ...$extra,
        ]);
    }
}
