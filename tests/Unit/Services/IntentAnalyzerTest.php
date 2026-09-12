<?php

namespace Tests\Unit\Services;

use App\Contracts\AIProviderInterface;
use App\Exceptions\AIProviderException;
use App\Exceptions\InvalidIntentException;
use App\Services\AI\IntentAnalyzer;
use App\Services\AI\NullAIProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class IntentAnalyzerTest extends TestCase
{
    // (a) Intenção válida

    public function test_analisa_intencao_valida_e_retorna_a_estrutura_esperada(): void
    {
        $analyzer = new IntentAnalyzer(new NullAIProvider);

        $result = $analyzer->analyze(
            "Criar uma API REST em Laravel com PHP seguindo Clean Architecture.\n"
            .'Não deve usar pacotes pagos.'
        );

        $this->assertSame(
            ['objective', 'technologies', 'architecture', 'constraints', 'type'],
            array_keys($result)
        );

        $this->assertSame(
            'Criar uma API REST em Laravel com PHP seguindo Clean Architecture',
            $result['objective']
        );
        $this->assertSame(['PHP', 'Laravel'], $result['technologies']);
        $this->assertSame('Clean Architecture', $result['architecture']);
        $this->assertSame(['Não deve usar pacotes pagos'], $result['constraints']);
        $this->assertSame('feature', $result['type']);
    }

    public function test_o_contrato_de_tipos_do_retorno_e_sempre_respeitado(): void
    {
        $analyzer = new IntentAnalyzer(new NullAIProvider);

        $result = $analyzer->analyze('Escrever a documentação de instalação do projeto no README.');

        $this->assertIsString($result['objective']);
        $this->assertIsArray($result['technologies']);
        $this->assertTrue(is_string($result['architecture']) || is_null($result['architecture']));
        $this->assertIsArray($result['constraints']);
        $this->assertContains($result['type'], IntentAnalyzer::TYPES);
        $this->assertSame('documentation', $result['type']);
    }

    // (b) Entrada vazia ou curta demais

    /**
     * @return array<string, array{0: string}>
     */
    public static function entradasVaziasProvider(): array
    {
        return [
            'string vazia' => [''],
            'apenas espaços' => ["   \t  "],
            'apenas quebras de linha' => ["\n\n\n"],
            'apenas marcação html' => ['<p><span></span></p>'],
            'apenas caracteres de controle' => ["\x00\x07\x1F"],
        ];
    }

    #[DataProvider('entradasVaziasProvider')]
    public function test_entrada_vazia_lanca_excecao_de_dominio(string $input): void
    {
        $analyzer = new IntentAnalyzer(new NullAIProvider);

        $this->expectException(InvalidIntentException::class);
        $this->expectExceptionMessage('vazia');

        $analyzer->analyze($input);
    }

    public function test_entrada_curta_demais_lanca_excecao_de_dominio(): void
    {
        $analyzer = new IntentAnalyzer(new NullAIProvider);

        $this->expectException(InvalidIntentException::class);
        $this->expectExceptionMessage('9 caractere(s), mínimo de 10');

        $analyzer->analyze('  Criar API  ');
    }

    public function test_entrada_no_limite_minimo_e_aceita(): void
    {
        $analyzer = new IntentAnalyzer(new NullAIProvider);

        $result = $analyzer->analyze('Criar API!');

        $this->assertSame('Criar API!', $result['objective']);
        $this->assertSame('feature', $result['type']);
    }

    public function test_o_provedor_nao_e_chamado_quando_a_entrada_e_invalida(): void
    {
        $provider = $this->spyProvider();
        $analyzer = new IntentAnalyzer($provider);

        try {
            $analyzer->analyze('oi');
        } catch (InvalidIntentException) {
            // esperado
        }

        $this->assertSame(0, $provider->calls);
    }

    // (c) Falha do provedor de IA

    public function test_falha_do_provedor_vira_excecao_de_infraestrutura(): void
    {
        $analyzer = new IntentAnalyzer($this->failingProvider(
            new RuntimeException('503 Service Unavailable')
        ));

        $this->expectException(AIProviderException::class);
        $this->expectExceptionMessage('O provedor de IA "fake-llm" falhou ao analisar a intenção.');

        $analyzer->analyze('Criar um serviço de cobrança recorrente em Laravel.');
    }

    public function test_a_causa_original_da_falha_do_provedor_e_preservada(): void
    {
        $original = new RuntimeException('503 Service Unavailable');
        $analyzer = new IntentAnalyzer($this->failingProvider($original));

        try {
            $analyzer->analyze('Criar um serviço de cobrança recorrente em Laravel.');
            $this->fail('Esperava uma AIProviderException.');
        } catch (AIProviderException $e) {
            $this->assertSame($original, $e->getPrevious());
        }
    }

    public function test_erro_de_tipo_do_provedor_tambem_e_encapsulado(): void
    {
        $analyzer = new IntentAnalyzer($this->failingProvider(new \TypeError('retorno inválido')));

        $this->expectException(AIProviderException::class);

        $analyzer->analyze('Criar um serviço de cobrança recorrente em Laravel.');
    }

    // Normalização defensiva do retorno do provedor

    public function test_payload_incompleto_do_provedor_e_normalizado(): void
    {
        $analyzer = new IntentAnalyzer($this->fakeProvider(['objective' => 'Migrar o banco']));

        $result = $analyzer->analyze('Migrar o banco de dados para PostgreSQL.');

        $this->assertSame([
            'objective' => 'Migrar o banco',
            'technologies' => [],
            'architecture' => null,
            'constraints' => [],
            'type' => 'general',
        ], $result);
    }

    public function test_listas_em_formato_de_string_sao_convertidas_em_array(): void
    {
        $analyzer = new IntentAnalyzer($this->fakeProvider([
            'technologies' => 'PHP, Laravel; PostgreSQL',
            'constraints' => "Sem libs pagas\nCobertura mínima de 80%",
        ]));

        $result = $analyzer->analyze('Migrar o banco de dados para PostgreSQL.');

        $this->assertSame(['PHP', 'Laravel', 'PostgreSQL'], $result['technologies']);
        $this->assertSame(['Sem libs pagas', 'Cobertura mínima de 80%'], $result['constraints']);
    }

    public function test_itens_vazios_invalidos_e_duplicados_sao_descartados_das_listas(): void
    {
        $analyzer = new IntentAnalyzer($this->fakeProvider([
            'technologies' => ['PHP', ' php ', '', null, ['aninhado'], 'Laravel'],
        ]));

        $result = $analyzer->analyze('Migrar o banco de dados para PostgreSQL.');

        $this->assertSame(['PHP', 'Laravel'], $result['technologies']);
    }

    public function test_tipo_desconhecido_cai_para_o_valor_padrao(): void
    {
        $analyzer = new IntentAnalyzer($this->fakeProvider(['type' => 'algo-que-nao-existe']));

        $result = $analyzer->analyze('Migrar o banco de dados para PostgreSQL.');

        $this->assertSame(IntentAnalyzer::DEFAULT_TYPE, $result['type']);
    }

    public function test_tipo_valido_e_normalizado_para_minusculas(): void
    {
        $analyzer = new IntentAnalyzer($this->fakeProvider(['type' => ' BugFix ']));

        $result = $analyzer->analyze('Corrigir o erro de login intermitente.');

        $this->assertSame('bugfix', $result['type']);
    }

    public function test_objetivo_ausente_usa_a_primeira_frase_da_entrada(): void
    {
        $analyzer = new IntentAnalyzer($this->fakeProvider(['objective' => '   ']));

        $result = $analyzer->analyze('Migrar o banco para PostgreSQL. Depois ajustar as migrations.');

        $this->assertSame('Migrar o banco para PostgreSQL.', $result['objective']);
    }

    public function test_objetivo_muito_longo_e_truncado(): void
    {
        $analyzer = new IntentAnalyzer($this->fakeProvider(['objective' => str_repeat('a', 500)]));

        $result = $analyzer->analyze('Migrar o banco de dados para PostgreSQL.');

        $this->assertSame(IntentAnalyzer::MAX_OBJECTIVE_LENGTH, mb_strlen($result['objective']));
    }

    // Sanitização

    public function test_o_provedor_recebe_a_entrada_sanitizada(): void
    {
        $provider = $this->spyProvider();
        $analyzer = new IntentAnalyzer($provider);

        $analyzer->analyze("  <b>Criar</b>   um   serviço\r\n\r\n\r\n  de   billing \x00 ");

        $this->assertSame("Criar um serviço\n\nde billing", $provider->lastInput);
        $this->assertSame(1, $provider->calls);
    }

    public function test_entrada_acima_do_limite_e_truncada(): void
    {
        $provider = $this->spyProvider();
        $analyzer = new IntentAnalyzer($provider);

        $analyzer->analyze(str_repeat(
            'Criar uma API REST em Laravel com PHP. ',
            40
        ));

        $this->assertSame(IntentAnalyzer::MAX_INPUT_LENGTH, mb_strlen((string) $provider->lastInput));
    }

    // Helpers

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fakeProvider(array $payload): AIProviderInterface
    {
        return new class($payload) implements AIProviderInterface
        {
            /**
             * @param  array<string, mixed>  $payload
             */
            public function __construct(private array $payload) {}

            public function analyzeIntent(string $userInput): array
            {
                return $this->payload;
            }

            public function composePrompt(string $instruction, string $templateBody, array $variables): string
            {
                return $templateBody;
            }

            public function generateStructuredPrompt(string $intencao, string $templateBody, array $variables): array
            {
                return [
                    'valido' => true,
                    'motivo_rejeicao' => null,
                    'prompt_gerado' => $templateBody,
                ];
            }

            public function name(): string
            {
                return 'fake-llm';
            }
        };
    }

    private function failingProvider(\Throwable $error): AIProviderInterface
    {
        return new class($error) implements AIProviderInterface
        {
            public function __construct(private \Throwable $error) {}

            public function analyzeIntent(string $userInput): array
            {
                throw $this->error;
            }

            public function composePrompt(string $instruction, string $templateBody, array $variables): string
            {
                throw $this->error;
            }

            public function generateStructuredPrompt(string $intencao, string $templateBody, array $variables): array
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
            public int $calls = 0;

            public ?string $lastInput = null;

            public function analyzeIntent(string $userInput): array
            {
                $this->calls++;
                $this->lastInput = $userInput;

                return [];
            }

            public function composePrompt(string $instruction, string $templateBody, array $variables): string
            {
                return $templateBody;
            }

            public function generateStructuredPrompt(string $intencao, string $templateBody, array $variables): array
            {
                return [
                    'valido' => true,
                    'motivo_rejeicao' => null,
                    'prompt_gerado' => $templateBody,
                ];
            }

            public function name(): string
            {
                return 'spy';
            }
        };
    }
}
