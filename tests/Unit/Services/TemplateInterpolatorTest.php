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
}
