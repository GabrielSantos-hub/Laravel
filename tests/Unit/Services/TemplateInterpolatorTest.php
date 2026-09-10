<?php

namespace Tests\Unit\Services;

use App\Services\AI\TemplateInterpolator;
use PHPUnit\Framework\TestCase;

class TemplateInterpolatorTest extends TestCase
{
    private TemplateInterpolator $interpolator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->interpolator = new TemplateInterpolator;
    }

    public function test_substitui_os_placeholders_conhecidos(): void
    {
        $resultado = $this->interpolator->render(
            'Escreva em {language} seguindo {architecture}.',
            ['language' => 'PHP', 'architecture' => 'Clean Architecture']
        );

        $this->assertSame('Escreva em PHP seguindo Clean Architecture.', $resultado);
    }

    public function test_mantem_o_bloco_condicional_quando_a_variavel_tem_valor(): void
    {
        $resultado = $this->interpolator->render(
            'Use {language}{% if framework %} com {framework}{% endif %}.',
            ['language' => 'PHP', 'framework' => 'Laravel']
        );

        $this->assertSame('Use PHP com Laravel.', $resultado);
    }

    public function test_remove_o_bloco_condicional_quando_a_variavel_esta_vazia_ou_ausente(): void
    {
        $corpo = 'Use {language}{% if framework %} com {framework}{% endif %}.';

        $this->assertSame(
            'Use PHP.',
            $this->interpolator->render($corpo, ['language' => 'PHP', 'framework' => ''])
        );

        $this->assertSame(
            'Use PHP.',
            $this->interpolator->render($corpo, ['language' => 'PHP'])
        );
    }

    public function test_chaves_desconhecidas_no_texto_sao_preservadas(): void
    {
        $resultado = $this->interpolator->render(
            'Responda em {language} no formato {"nome": "valor"}.',
            ['language' => 'PHP']
        );

        $this->assertSame('Responda em PHP no formato {"nome": "valor"}.', $resultado);
    }

    public function test_valores_nao_escalares_viram_string_vazia(): void
    {
        $resultado = $this->interpolator->render(
            'Use {language}{% if framework %} com {framework}{% endif %}.',
            ['language' => 'PHP', 'framework' => ['Laravel']]
        );

        $this->assertSame('Use PHP.', $resultado);
    }

    public function test_render_normaliza_o_espacamento_residual(): void
    {
        $resultado = $this->interpolator->render(
            "  Tarefa:   {objective}   \r\n\r\n\r\n  Fim.  ",
            ['objective' => 'Criar API']
        );

        $this->assertSame("Tarefa: Criar API\n\nFim.", $resultado);
    }

    public function test_resolve_placeholders_preserva_a_indentacao_original(): void
    {
        $resultado = $this->interpolator->resolvePlaceholders(
            "Exemplo em {language}:\n    return true;",
            ['language' => 'PHP']
        );

        $this->assertSame("Exemplo em PHP:\n    return true;", $resultado);
    }

    public function test_extrai_os_marcadores_dinamicos_na_ordem_de_aparicao(): void
    {
        $variaveis = $this->interpolator->extractVariables(
            'Crie o CRUD de {NOME_DA_ENTIDADE} em {language} com o campo {CAMPO_BANCO}. Contexto: {user_input}.'
        );

        $this->assertSame(['NOME_DA_ENTIDADE', 'CAMPO_BANCO'], $variaveis);
    }

    public function test_marcadores_repetidos_aparecem_uma_unica_vez(): void
    {
        $variaveis = $this->interpolator->extractVariables(
            'Tabela {CAMPO}, coluna {CAMPO}, índice de {CAMPO}.'
        );

        $this->assertSame(['CAMPO'], $variaveis);
    }

    public function test_extrai_tambem_a_variavel_de_um_bloco_condicional(): void
    {
        $variaveis = $this->interpolator->extractVariables(
            'Base: {user_input}{% if REGRA_DE_NEGOCIO %} Regra: {REGRA_DE_NEGOCIO}{% endif %}'
        );

        $this->assertSame(['REGRA_DE_NEGOCIO'], $variaveis);
    }

    public function test_trechos_de_codigo_e_json_nao_viram_variaveis(): void
    {
        $variaveis = $this->interpolator->extractVariables(
            'Responda no formato {"nome": "valor"} usando {} como padrão e {2} itens.'
        );

        $this->assertSame([], $variaveis);
    }

    public function test_as_variaveis_reservadas_podem_ser_incluidas_sob_demanda(): void
    {
        $corpo = 'Em {language}: {NOME_DA_ENTIDADE}.';

        $this->assertSame(['NOME_DA_ENTIDADE'], $this->interpolator->extractVariables($corpo));
        $this->assertSame(
            ['language', 'NOME_DA_ENTIDADE'],
            $this->interpolator->extractVariables($corpo, includeReserved: true)
        );
    }
}
