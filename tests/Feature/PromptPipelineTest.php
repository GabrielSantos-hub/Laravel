<?php

namespace Tests\Feature;

use App\Models\Architecture;
use App\Models\Framework;
use App\Models\Language;
use App\Models\Template;
use App\Services\AI\IntentAnalyzer;
use App\Services\AI\PromptComposer;
use App\Services\AI\TemplateSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Amarra as três etapas do pipeline: analisar a intenção, escolher o template
 * e compor o prompt final, tudo resolvido pelo container com o driver padrão.
 */
class PromptPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_o_pipeline_completo_gera_o_prompt_final(): void
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
            'corpo_template' => "Você é um especialista em {language}{% if framework %} e no framework {framework}{% endif %}, seguindo {architecture}.\n\n"
                ."Tarefa: {user_input}\n\n"
                ."Restrições:\n{constraints}",
            'versao' => '1',
            'is_active' => true,
        ]);
        $template->languages()->attach($php);
        $template->frameworks()->attach($laravel);
        $template->architectures()->attach($clean);

        $ignorado = Template::query()->create([
            'nome' => 'Template genérico',
            'corpo_template' => 'Instruções gerais.',
            'versao' => '1',
            'is_active' => true,
        ]);

        $intencao = app(IntentAnalyzer::class)->analyze(
            "Criar uma API REST em Laravel com PHP seguindo Clean Architecture.\n"
            .'Não deve usar pacotes pagos.'
        );

        $selecionado = app(TemplateSelector::class)->select($intencao);

        $this->assertTrue($template->is($selecionado));
        $this->assertFalse($ignorado->is($selecionado));

        $prompt = app(PromptComposer::class)->compose($intencao, $selecionado);

        $this->assertStringContainsString('Você é um especialista em PHP e no framework Laravel, seguindo Clean Architecture.', $prompt);
        $this->assertStringContainsString('Regra de negócio', $prompt);
        $this->assertStringContainsString('Requisitos implícitos', $prompt);
        $this->assertStringContainsString('Fluxo do usuário', $prompt);
        $this->assertStringContainsString("Restrições:\n- Não deve usar pacotes pagos", $prompt);
        $this->assertStringNotContainsString('"Criar uma API REST', $prompt);
    }

    public function test_o_pipeline_escolhe_o_template_classificado_pela_linguagem(): void
    {
        $php = Language::query()->create(['nome' => 'PHP', 'slug' => 'php']);

        $classificado = Template::query()->create([
            'nome' => 'Template automático',
            'corpo_template' => 'Automático: {user_input}',
            'versao' => '1',
            'is_active' => true,
        ]);
        $classificado->languages()->attach($php);

        Template::query()->create([
            'nome' => 'Template avulso',
            'corpo_template' => 'Avulso: {user_input}',
            'versao' => '1',
            'is_active' => true,
        ]);

        $intencao = app(IntentAnalyzer::class)->analyze('Criar um relatório de vendas em PHP.');
        $selecionado = app(TemplateSelector::class)->select($intencao);

        $this->assertTrue($classificado->is($selecionado));
        $prompt = app(PromptComposer::class)->compose($intencao, $selecionado);
        $this->assertStringStartsWith('Automático:', $prompt);
        $this->assertStringContainsString('Regra de negócio', $prompt);
        $this->assertStringContainsString('Requisitos implícitos', $prompt);
    }
}
