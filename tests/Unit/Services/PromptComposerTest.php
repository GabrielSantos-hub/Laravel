<?php

namespace Tests\Unit\Services;

use App\Contracts\AIProviderInterface;
use App\Models\Template;
use App\Services\AI\NullAIProvider;
use App\Services\AI\PromptComposer;
use App\Services\AI\TemplateInterpolator;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class PromptComposerTest extends TestCase
{
    // (a) Composição bem-sucedida

    public function test_composicao_bem_sucedida_retorna_o_prompt_do_provedor(): void
    {
        $composer = new PromptComposer($this->fakeProvider('Prompt final refinado pelo modelo.'));

        $resultado = $composer->compose($this->intent(), $this->template('Corpo com {user_input}.'));

        $this->assertSame('Prompt final refinado pelo modelo.', $resultado);
    }

    public function test_o_provedor_recebe_instrucao_corpo_e_variaveis_resolvidas(): void
    {
        $provider = $this->spyProvider();

        (new PromptComposer($provider))->compose(
            $this->intent(),
            $this->template('Corpo cru com {user_input}.')
        );

        $this->assertStringContainsString('engenheiro de prompts', $provider->lastInstruction);
        $this->assertSame('Corpo cru com {user_input}.', $provider->lastBody);

        $this->assertSame([
            'user_input' => 'Criar uma API de cobrança recorrente',
            'objective' => 'Criar uma API de cobrança recorrente',
            'type' => 'feature',
            'architecture' => 'Clean Architecture',
            'technologies' => 'PHP, Laravel',
            'language' => 'PHP',
            'framework' => 'Laravel',
            'constraints' => "- Sem bibliotecas pagas\n- Cobertura mínima de 80%",
        ], $provider->lastVariables);
    }

    public function test_cerca_de_codigo_do_modelo_e_removida(): void
    {
        $composer = new PromptComposer($this->fakeProvider("```markdown\nPrompt final.\n```"));

        $resultado = $composer->compose($this->intent(), $this->template('Corpo.'));

        $this->assertSame('Prompt final.', $resultado);
    }

    public function test_placeholders_remanescentes_na_saida_do_modelo_sao_resolvidos(): void
    {
        $composer = new PromptComposer($this->fakeProvider('Implemente {user_input} usando {framework}.'));

        $resultado = $composer->compose($this->intent(), $this->template('Corpo.'));

        $this->assertSame('Implemente Criar uma API de cobrança recorrente usando Laravel.', $resultado);
    }

    public function test_a_indentacao_da_saida_do_modelo_e_preservada(): void
    {
        $composer = new PromptComposer($this->fakeProvider("Exemplo:\n    return true;"));

        $resultado = $composer->compose($this->intent(), $this->template('Corpo.'));

        $this->assertSame("Exemplo:\n    return true;", $resultado);
    }

    // (b) Fallback gracioso

    public function test_falha_do_provedor_cai_para_interpolacao_simples(): void
    {
        $composer = new PromptComposer($this->failingProvider(new RuntimeException('timeout')));

        $resultado = $composer->compose($this->intent(), $this->template(
            'Você é especialista em {language}{% if framework %} e no framework {framework}{% endif %}.'
            ."\n".'Tarefa: {user_input}.'
        ));

        $this->assertSame(
            "Você é especialista em PHP e no framework Laravel.\n"
            .'Tarefa: Criar uma API de cobrança recorrente.',
            $resultado
        );
    }

    public function test_bloco_condicional_some_no_fallback_quando_nao_ha_framework(): void
    {
        $composer = new PromptComposer($this->failingProvider(new RuntimeException('timeout')));

        $resultado = $composer->compose(
            $this->intent(technologies: ['PHP']),
            $this->template('Você é especialista em {language}{% if framework %} e no framework {framework}{% endif %}.')
        );

        $this->assertSame('Você é especialista em PHP.', $resultado);
    }

    public function test_saida_vazia_do_provedor_dispara_o_fallback(): void
    {
        $composer = new PromptComposer($this->fakeProvider("   \n  "));

        $resultado = $composer->compose($this->intent(), $this->template('Tarefa: {user_input}.'));

        $this->assertSame('Tarefa: Criar uma API de cobrança recorrente.', $resultado);
    }

    public function test_erro_de_tipo_do_provedor_tambem_cai_no_fallback(): void
    {
        $composer = new PromptComposer($this->failingProvider(new \TypeError('retorno inválido')));

        $resultado = $composer->compose($this->intent(), $this->template('Tarefa: {user_input}.'));

        $this->assertSame('Tarefa: Criar uma API de cobrança recorrente.', $resultado);
    }

    public function test_a_falha_do_provedor_e_registrada_no_log(): void
    {
        $logger = $this->spyLogger();

        (new PromptComposer(
            $this->failingProvider(new RuntimeException('503 Service Unavailable')),
            new TemplateInterpolator,
            $logger
        ))->compose($this->intent(), $this->template('Tarefa: {user_input}.'));

        $this->assertCount(1, $logger->records);
        $this->assertSame('warning', $logger->records[0]['level']);
        $this->assertStringContainsString('503 Service Unavailable', $logger->records[0]['context']['exception']);
        $this->assertArrayHasKey('template_id', $logger->records[0]['context']);
    }

    // Provedor offline (driver padrão)

    public function test_provedor_offline_compoe_sem_disparar_o_fallback(): void
    {
        $logger = $this->spyLogger();

        $resultado = (new PromptComposer(new NullAIProvider, new TemplateInterpolator, $logger))
            ->compose($this->intent(), $this->template('Tarefa: {user_input} em {language}.'));

        $this->assertSame('Tarefa: Criar uma API de cobrança recorrente em PHP.', $resultado);
        $this->assertSame([], $logger->records);
    }

    public function test_restricoes_da_intencao_viram_lista_no_prompt(): void
    {
        $resultado = (new PromptComposer(new NullAIProvider))
            ->compose($this->intent(), $this->template("Restrições:\n{constraints}"));

        $this->assertSame(
            "Restrições:\n- Sem bibliotecas pagas\n- Cobertura mínima de 80%",
            $resultado
        );
    }

    public function test_intencao_malformada_nao_quebra_a_composicao(): void
    {
        $composer = new PromptComposer($this->failingProvider(new RuntimeException('timeout')));

        $resultado = $composer->compose([], $this->template('Tarefa: {user_input} em {language}.'));

        $this->assertSame('Tarefa: em .', $resultado);
    }

    // Helpers

    /**
     * @param  array<int, string>  $technologies
     * @return array<string, mixed>
     */
    private function intent(array $technologies = ['PHP', 'Laravel']): array
    {
        return [
            'objective' => 'Criar uma API de cobrança recorrente',
            'technologies' => $technologies,
            'architecture' => 'Clean Architecture',
            'constraints' => ['Sem bibliotecas pagas', 'Cobertura mínima de 80%'],
            'type' => 'feature',
        ];
    }

    private function template(string $corpo): Template
    {
        return new Template([
            'nome' => 'Template de teste',
            'corpo_template' => $corpo,
            'versao' => '1',
            'is_active' => true,
        ]);
    }

    private function fakeProvider(string $output): AIProviderInterface
    {
        return new class($output) implements AIProviderInterface
        {
            public function __construct(private string $output) {}

            public function analyzeIntent(string $userInput): array
            {
                return [];
            }

            public function composePrompt(string $instruction, string $templateBody, array $variables): string
            {
                return $this->output;
            }

            public function name(): string
            {
                return 'fake-llm';
            }
        };
    }

    private function failingProvider(Throwable $error): AIProviderInterface
    {
        return new class($error) implements AIProviderInterface
        {
            public function __construct(private Throwable $error) {}

            public function analyzeIntent(string $userInput): array
            {
                throw $this->error;
            }

            public function composePrompt(string $instruction, string $templateBody, array $variables): string
            {
                throw $this->error;
            }

            public function name(): string
            {
                return 'fake-llm';
            }
        };
    }

    private function spyProvider(): AIProviderInterface
    {
        return new class implements AIProviderInterface
        {
            public string $lastInstruction = '';

            public string $lastBody = '';

            /** @var array<string, string> */
            public array $lastVariables = [];

            public function analyzeIntent(string $userInput): array
            {
                return [];
            }

            public function composePrompt(string $instruction, string $templateBody, array $variables): string
            {
                $this->lastInstruction = $instruction;
                $this->lastBody = $templateBody;
                $this->lastVariables = $variables;

                return 'ok';
            }

            public function name(): string
            {
                return 'spy';
            }
        };
    }

    private function spyLogger(): LoggerInterface
    {
        return new class extends AbstractLogger
        {
            /** @var array<int, array{level: mixed, message: string, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };
    }
}
