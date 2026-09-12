<?php

namespace Tests\Unit\Services;

use App\Contracts\AIProviderInterface;
use App\Services\AI\NullAIProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NullAIProviderTest extends TestCase
{
    private NullAIProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new NullAIProvider;
    }

    public function test_implementa_o_contrato_de_provedor(): void
    {
        $this->assertInstanceOf(AIProviderInterface::class, $this->provider);
        $this->assertSame('null', $this->provider->name());
    }

    public function test_identifica_tecnologias_sem_falsos_positivos(): void
    {
        $result = $this->provider->analyzeIntent('Preciso de um worker em Java lendo do Redis.');

        $this->assertSame(['Java', 'Redis'], $result['technologies']);
        $this->assertNotContains('JavaScript', $result['technologies']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function tiposProvider(): array
    {
        return [
            'feature' => ['Implementar o cadastro de clientes.', 'feature'],
            'crud' => ['Faça um crud de cadastro de clientes.', 'feature'],
            'login' => ['Faça um sistema de login com recuperação de senha.', 'feature'],
            'bugfix' => ['Corrigir o erro ao salvar o pedido.', 'bugfix'],
            'refactor' => ['Refatorar o serviço de faturamento.', 'refactor'],
            'test' => ['Escrever testes unitários para o repositório.', 'test'],
            'documentation' => ['Documentar os endpoints da API.', 'documentation'],
            'general' => ['Preciso de algo bacana para o cliente.', 'general'],
        ];
    }

    #[DataProvider('tiposProvider')]
    public function test_classifica_o_tipo_da_intencao(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->provider->analyzeIntent($input)['type']);
    }

    public function test_arquitetura_e_nula_quando_nao_e_mencionada(): void
    {
        $result = $this->provider->analyzeIntent('Implementar o cadastro de clientes.');

        $this->assertNull($result['architecture']);
    }

    public function test_extrai_restricoes_das_frases_do_usuario(): void
    {
        $result = $this->provider->analyzeIntent(
            "Criar o módulo de relatórios.\n"
            ."Não deve depender de bibliotecas pagas.\n"
            .'Apenas PHP puro.'
        );

        $this->assertSame(
            ['Não deve depender de bibliotecas pagas', 'Apenas PHP puro'],
            $result['constraints']
        );
    }

    public function test_keysmash_nao_e_perdoado_por_termos_tecnicos(): void
    {
        $resultado = $this->provider->generateStructuredPrompt(
            'asdfghjk lkjhgf docker kubernetes zxcvbnm criar banco de dados',
            'Tarefa: {user_input}',
            ['user_input' => 'ruído']
        );

        $this->assertFalse($resultado['valido']);
    }

    public function test_frase_cotidiana_sem_escopo_de_software_e_recusada(): void
    {
        $resultado = $this->provider->generateStructuredPrompt(
            'hoje o dia está muito bonito para comer bola e sapato com manteiga',
            'Tarefa: {user_input}',
            ['user_input' => 'ruído']
        );

        $this->assertFalse($resultado['valido']);
    }

    public function test_ruido_de_comida_misturado_com_jargao_e_recusado(): void
    {
        $resultado = $this->provider->generateStructuredPrompt(
            'api rest json banana frita com queijo e cebola roxa rodando em background',
            'Tarefa: {user_input}',
            ['user_input' => 'ruído']
        );

        $this->assertFalse($resultado['valido']);
        $this->assertSame('', $resultado['prompt_gerado']);
    }

    public function test_few_shot_de_salada_de_palavras_e_recusado(): void
    {
        $resultado = $this->provider->generateStructuredPrompt(
            'papo rato padeiro',
            'Tarefa: {user_input}',
            ['user_input' => 'papo rato padeiro']
        );

        $this->assertFalse($resultado['valido']);
        $this->assertSame(
            'A entrada não apresenta um objetivo ou escopo de software coerente.',
            $resultado['motivo_rejeicao']
        );
        $this->assertSame('', $resultado['prompt_gerado']);
    }

    public function test_keysmash_e_recusado_na_saida_estruturada(): void
    {
        $resultado = $this->provider->generateStructuredPrompt(
            'asdfgh qwerty zxcvbn',
            'Tarefa: {user_input}',
            ['user_input' => 'asdfgh qwerty zxcvbn']
        );

        $this->assertFalse($resultado['valido']);
        $this->assertNotEmpty($resultado['motivo_rejeicao']);
        $this->assertSame('', $resultado['prompt_gerado']);
    }

    public function test_dominio_aberto_nao_e_recusado_offline(): void
    {
        $resultado = $this->provider->generateStructuredPrompt(
            'Calcular a dosagem de insulina no prontuário eletrônico do hospital.',
            'Tarefa: {user_input}',
            ['user_input' => 'Briefing clínico']
        );

        $this->assertTrue($resultado['valido']);
        $this->assertNull($resultado['motivo_rejeicao']);
        $this->assertStringContainsString('Briefing clínico', $resultado['prompt_gerado']);
    }

    public function test_e_deterministico_e_nunca_lanca_excecao(): void
    {
        $input = 'Migrar tudo para microserviços com Docker e PostgreSQL.';

        $this->assertSame(
            $this->provider->analyzeIntent($input),
            $this->provider->analyzeIntent($input)
        );
        $this->assertSame([], $this->provider->analyzeIntent('')['technologies']);
    }
}
