<?php

namespace Tests\Unit\Services;

use App\Exceptions\InvalidIntentException;
use App\Exceptions\NoCompatibleTemplateException;
use App\Models\Architecture;
use App\Models\Framework;
use App\Models\Language;
use App\Models\Template;
use App\Services\PromptPipelineResult;
use App\Services\PromptPipelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
