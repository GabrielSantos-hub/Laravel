<?php

namespace Tests\Feature;

use App\Models\Architecture;
use App\Models\Framework;
use App\Models\Language;
use App\Models\Template;
use App\Models\User;
use App\Services\AI\TemplateSelector;
use App\Services\Guardrails\InputSanityGuardrail;
use App\Services\PromptBuilderService;
use App\Services\PromptPipelineService;
use Database\Seeders\ArchitectureSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogPopulationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>, 2: array<string, mixed>}>
     */
    public static function novosTemplatesProvider(): array
    {
        return [
            'testes' => [
                'geracao-testes-aaa-tdd',
                [
                    'type' => 'test',
                    'objective' => 'Escrever testes unitários AAA TDD para o repositório',
                    'technologies' => [],
                    'architecture' => null,
                ],
                [
                    'type' => 'test',
                    'objective' => 'Cobrir com testes de integração o padrão AAA TDD',
                    'technologies' => ['PHP'],
                    'architecture' => null,
                ],
            ],
            'refatoracao' => [
                'refatoracao-segura',
                [
                    'type' => 'refactor',
                    'objective' => 'Refatorar com plano em passos preservando o comportamento',
                    'technologies' => [],
                    'architecture' => null,
                ],
                [
                    'type' => 'refactor',
                    'objective' => 'Refatoração segura sem mudar o comportamento observado',
                    'technologies' => [],
                    'architecture' => null,
                ],
            ],
            'owasp' => [
                'revisao-seguranca-owasp',
                [
                    'type' => 'security',
                    'objective' => 'Revisar segurança OWASP de um trecho da API',
                    'technologies' => [],
                    'architecture' => null,
                ],
                [
                    'type' => 'security',
                    'objective' => 'Checar o OWASP Top 10 nesta funcionalidade de login',
                    'technologies' => [],
                    'architecture' => null,
                ],
            ],
            'depuracao' => [
                'depuracao-stack-trace',
                [
                    'type' => 'bugfix',
                    'objective' => 'Depurar a partir do stack trace do erro 500',
                    'technologies' => [],
                    'architecture' => null,
                ],
                [
                    'type' => 'bugfix',
                    'objective' => 'Ler o stack trace e achar a causa do exception',
                    'technologies' => [],
                    'architecture' => null,
                ],
            ],
            'performance' => [
                'otimizacao-performance',
                [
                    'type' => 'analysis',
                    'objective' => 'Otimizar performance de consultas SQL e gargalos',
                    'technologies' => [],
                    'architecture' => null,
                ],
                [
                    'type' => 'analysis',
                    'objective' => 'Atacar gargalos de consultas SQL lentas na listagem',
                    'technologies' => ['SQL'],
                    'architecture' => null,
                ],
            ],
            'stories' => [
                'user-stories-bdd-gherkin',
                [
                    'type' => 'documentation',
                    'objective' => 'Escrever user stories com critérios de aceite em Gherkin',
                    'technologies' => [],
                    'architecture' => null,
                ],
                [
                    'type' => 'documentation',
                    'objective' => 'Detalhar critérios de aceite BDD Gherkin da user story',
                    'technologies' => [],
                    'architecture' => null,
                ],
            ],
            'cicd' => [
                'pipeline-ci-cd',
                [
                    'type' => 'feature',
                    'objective' => 'Montar pipeline de CI/CD com build e deploy',
                    'technologies' => [],
                    'architecture' => null,
                ],
                [
                    'type' => 'feature',
                    'objective' => 'Configurar pipeline CI/CD no GitHub Actions',
                    'technologies' => [],
                    'architecture' => null,
                ],
            ],
            'migracao' => [
                'migracao-versao-framework',
                [
                    'type' => 'refactor',
                    'objective' => 'Migrar para a próxima versão do framework',
                    'technologies' => [],
                    'architecture' => null,
                ],
                [
                    'type' => 'refactor',
                    'objective' => 'Planejar a migração de versão do framework sem downtime',
                    'technologies' => [],
                    'architecture' => null,
                ],
            ],
            'commits' => [
                'mensagens-commit-changelog',
                [
                    'type' => 'documentation',
                    'objective' => 'Escrever mensagens de commit e changelog Conventional Commits',
                    'technologies' => [],
                    'architecture' => null,
                ],
                [
                    'type' => 'documentation',
                    'objective' => 'Redigir changelog e commit no padrão Conventional Commits',
                    'technologies' => [],
                    'architecture' => null,
                ],
            ],
        ];
    }

    public function test_seeder_roda_duas_vezes_sem_duplicar_nem_apagar_usuario(): void
    {
        $usuario = User::factory()->create([
            'email' => 'catalogo-idempotente@example.test',
            'name' => 'Usuario Catalogo',
        ]);

        $this->povoarCatalogo();
        $this->povoarCatalogo();

        $this->assertSame(15, Language::query()->count());
        $this->assertSame(15, Language::query()->distinct('slug')->count());
        $this->assertSame(32, Framework::query()->count());
        $this->assertSame(32, Framework::query()->distinct('slug')->count());
        $this->assertSame(16, Architecture::query()->count());
        $this->assertSame(16, Architecture::query()->distinct('nome')->count());
        $this->assertSame(26, Template::query()->count());
        $this->assertSame(26, Template::query()->where('is_active', true)->count());
        $this->assertSame(26, Template::query()->whereNotNull('slug')->distinct('slug')->count());

        $this->assertTrue(User::query()->whereKey($usuario->id)->exists());
        $this->assertDatabaseHas('users', [
            'id' => $usuario->id,
            'email' => 'catalogo-idempotente@example.test',
        ]);
    }

    #[DataProvider('novosTemplatesProvider')]
    public function test_seletor_escolhe_cada_template_novo_em_duas_intencoes(
        string $slug,
        array $primeira,
        array $segunda,
    ): void {
        $this->povoarCatalogo();

        $seletor = new TemplateSelector;
        $esperado = Template::query()->where('slug', $slug)->sole();

        $this->assertTrue($esperado->is($seletor->select($primeira)), $slug.' / intenção 1');
        $this->assertTrue($esperado->is($seletor->select($segunda)), $slug.' / intenção 2');
    }

    public function test_no_code_e_lean_continuam_com_o_catalogo_oficial(): void
    {
        $this->povoarCatalogo();

        $pipeline = app(PromptPipelineService::class);

        $lean = $pipeline->generate('erro 500 no login');
        $linhasLean = preg_split('/\R/u', trim($lean->prompt)) ?: [];

        $this->assertStringStartsWith(PromptBuilderService::SECTION_ROLE, $lean->prompt);
        $this->assertStringContainsString('erro 500 no login', $lean->prompt);
        $this->assertLessThanOrEqual(InputSanityGuardrail::LEAN_MAX_LINES, count($linhasLean));

        $semCodigo = $pipeline->generate(
            'Elabore a especificação técnica do 2FA em prosa. Não quero código PHP.'
        );

        $this->assertStringContainsString(PromptBuilderService::NO_CODE_CONSTRAINT, $semCodigo->prompt);
        $this->assertDoesNotMatchRegularExpression('/```/', $semCodigo->prompt);
    }

    private function povoarCatalogo(): void
    {
        $this->seed(LanguageSeeder::class);
        $this->seed(ArchitectureSeeder::class);
        $this->seed(TemplateSeeder::class);
    }
}
