<?php

namespace Tests\Feature;

use App\Models\Architecture;
use App\Models\Framework;
use App\Models\Language;
use App\Models\Prompt;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->create();
    }

    // (a) Requisição válida

    public function test_requisicao_valida_gera_o_prompt_e_grava_no_historico(): void
    {
        $template = $this->templateClassificado();

        $resposta = $this->actingAs($this->usuario)->post(route('prompts.generate'), [
            'user_input' => 'Criar uma API REST em Laravel com PHP seguindo Clean Architecture.',
        ]);

        $resposta->assertRedirect(route('home'));
        $resposta->assertSessionHas('sucesso');
        $resposta->assertSessionHas(
            'last_output',
            'Especialista em PHP, Laravel, seguindo Clean Architecture. '
            .'Tarefa: Criar uma API REST em Laravel com PHP seguindo Clean Architecture'
        );

        $this->assertDatabaseCount('prompts', 1);

        $prompt = Prompt::query()->sole();
        $this->assertSame($this->usuario->id, $prompt->user_id);
        $this->assertSame($template->id, $prompt->template_id);
        $this->assertNull($prompt->architecture_id);
        $this->assertNull($prompt->language_id);
    }

    public function test_cliente_json_recebe_o_prompt_e_a_intencao_interpretada(): void
    {
        $template = $this->templateClassificado();

        $resposta = $this->actingAs($this->usuario)->postJson(route('prompts.generate'), [
            'user_input' => 'Criar uma API REST em Laravel com PHP seguindo Clean Architecture.',
        ]);

        $resposta->assertCreated();
        $resposta->assertJsonPath('template.id', $template->id);
        $resposta->assertJsonPath('manual_selection', false);
        $resposta->assertJsonPath('intent.type', 'feature');
        $resposta->assertJsonPath('intent.technologies', ['PHP', 'Laravel']);
        $resposta->assertJsonStructure(['prompt_id', 'prompt', 'template' => ['id', 'nome', 'versao'], 'intent']);
    }

    public function test_o_formulario_atual_continua_funcionando_com_o_campo_antigo(): void
    {
        $arquitetura = Architecture::query()->create(['nome' => 'MVC', 'descricao' => 'Camadas.']);
        $template = $this->templateClassificado();

        $resposta = $this->actingAs($this->usuario)->post(route('prompts.generate'), [
            'input_text' => 'Criar uma API REST em Laravel com PHP seguindo Clean Architecture.',
            'template_id' => $template->id,
            'architecture_id' => $arquitetura->id,
            'language_id' => Language::query()->firstOrFail()->id,
            'framework_id' => '',
        ]);

        $resposta->assertRedirect(route('home'));
        $resposta->assertSessionHas('last_output');

        $prompt = Prompt::query()->sole();
        $this->assertSame($arquitetura->id, $prompt->architecture_id);
        $this->assertNull($prompt->framework_id);
    }

    // (b) Entrada inválida

    public function test_entrada_ausente_retorna_erro_de_validacao_amigavel(): void
    {
        $this->templateClassificado();

        $resposta = $this->actingAs($this->usuario)->postJson(route('prompts.generate'), []);

        $resposta->assertUnprocessable();
        $resposta->assertJsonValidationErrors(['user_input' => 'Descreva o que você precisa gerar.']);
        $this->assertDatabaseCount('prompts', 0);
    }

    public function test_entrada_curta_demais_retorna_erro_de_validacao(): void
    {
        $this->templateClassificado();

        $resposta = $this->actingAs($this->usuario)
            ->postJson(route('prompts.generate'), ['user_input' => 'oi']);

        $resposta->assertUnprocessable();
        $resposta->assertJsonValidationErrorFor('user_input');
        $this->assertDatabaseCount('prompts', 0);
    }

    public function test_entrada_aleatoria_sem_template_compativel_nao_quebra_o_pipeline(): void
    {
        $this->templateClassificado();

        $resposta = $this->actingAs($this->usuario)
            ->postJson(route('prompts.generate'), ['user_input' => 'asdfgh qwerty zxcvbn']);

        $resposta->assertUnprocessable();
        $resposta->assertJsonValidationErrorFor('user_input');
        $this->assertStringContainsString(
            'Nenhum template compatível',
            $resposta->json('errors.user_input.0')
        );
        $this->assertDatabaseCount('prompts', 0);
    }

    public function test_erro_de_dominio_volta_para_o_formulario_com_o_input_preservado(): void
    {
        $this->templateClassificado();

        $resposta = $this->actingAs($this->usuario)
            ->from(route('home'))
            ->post(route('prompts.generate'), ['user_input' => 'asdfgh qwerty zxcvbn']);

        $resposta->assertRedirect(route('home'));
        $resposta->assertSessionHasErrors('user_input');
        $this->assertDatabaseCount('prompts', 0);
    }

    public function test_template_inativo_e_recusado_na_validacao(): void
    {
        $inativo = Template::query()->create([
            'nome' => 'Template arquivado',
            'corpo_template' => 'Corpo.',
            'versao' => '1',
            'is_active' => false,
        ]);

        $resposta = $this->actingAs($this->usuario)->postJson(route('prompts.generate'), [
            'user_input' => 'Criar uma API REST em Laravel com PHP.',
            'template_id' => $inativo->id,
        ]);

        $resposta->assertUnprocessable();
        $resposta->assertJsonValidationErrorFor('template_id');
        $this->assertDatabaseCount('prompts', 0);
    }

    public function test_visitante_nao_consegue_gerar_prompt(): void
    {
        $this->templateClassificado();

        $this->post(route('prompts.generate'), ['user_input' => 'Criar uma API em Laravel.'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('prompts', 0);
    }

    // (c) Seleção manual de template

    public function test_selecao_forcada_de_template_via_id(): void
    {
        $this->templateClassificado();

        $manual = Template::query()->create([
            'nome' => 'Template manual',
            'corpo_template' => 'Manual: {user_input}',
            'versao' => '1',
            'is_active' => true,
        ]);

        $resposta = $this->actingAs($this->usuario)->postJson(route('prompts.generate'), [
            'user_input' => 'Criar uma API REST em Laravel com PHP seguindo Clean Architecture.',
            'template_id' => $manual->id,
        ]);

        $resposta->assertCreated();
        $resposta->assertJsonPath('template.id', $manual->id);
        $resposta->assertJsonPath('manual_selection', true);
        $resposta->assertJsonPath(
            'prompt',
            'Manual: Criar uma API REST em Laravel com PHP seguindo Clean Architecture'
        );

        $this->assertSame($manual->id, Prompt::query()->sole()->template_id);
    }

    // (d) Autorização na exclusão

    public function test_usuario_nao_proprietario_nao_consegue_excluir_prompt(): void
    {
        $outro = User::factory()->create();
        $prompt = $this->prompt($outro);

        $resposta = $this->actingAs($this->usuario)->delete(route('prompts.destroy', $prompt));

        $resposta->assertForbidden();
        $this->assertDatabaseHas('prompts', ['id' => $prompt->id]);
    }

    public function test_proprietario_consegue_excluir_o_proprio_prompt(): void
    {
        $prompt = $this->prompt($this->usuario);

        $resposta = $this->actingAs($this->usuario)->delete(route('prompts.destroy', $prompt));

        $resposta->assertRedirect(route('home'));
        $resposta->assertSessionHas('sucesso');
        $this->assertDatabaseMissing('prompts', ['id' => $prompt->id]);
    }

    public function test_prompt_orfao_nao_pode_ser_excluido_por_ninguem(): void
    {
        $prompt = $this->prompt(null);

        $this->actingAs($this->usuario)
            ->delete(route('prompts.destroy', $prompt))
            ->assertForbidden();

        $this->assertDatabaseHas('prompts', ['id' => $prompt->id]);
    }

    public function test_visitante_nao_consegue_excluir_prompt(): void
    {
        $prompt = $this->prompt($this->usuario);

        $this->delete(route('prompts.destroy', $prompt))->assertRedirect(route('login'));

        $this->assertDatabaseHas('prompts', ['id' => $prompt->id]);
    }

    // (e) A tela de geração

    public function test_a_tela_oferece_o_modo_automatico_e_o_select_de_template_e_opcional(): void
    {
        $template = $this->templateClassificado();

        $resposta = $this->actingAs($this->usuario)->get(route('home'));

        $resposta->assertOk();
        $resposta->assertSee('🤖 Automático (A IA escolhe o melhor template para mim)', false);
        $resposta->assertSee($template->nome);

        preg_match('/<select name="template_id"([^>]*)>/', $resposta->getContent(), $atributos);

        $this->assertNotEmpty($atributos, 'O select de template não foi renderizado.');
        $this->assertStringNotContainsString('required', $atributos[1]);
    }

    public function test_erros_de_validacao_sao_exibidos_no_campo_correspondente(): void
    {
        $this->templateClassificado();

        $resposta = $this->actingAs($this->usuario)
            ->from(route('home'))
            ->followingRedirects()
            ->post(route('prompts.generate'), ['user_input' => 'oi']);

        $resposta->assertOk();
        $resposta->assertSee('Revise os campos destacados abaixo.');
        $resposta->assertSee('invalid-feedback', false);
        $resposta->assertSee('Descreva sua intenção com mais detalhes', false);
    }

    private function prompt(?User $dono): Prompt
    {
        return Prompt::query()->create([
            'user_id' => $dono?->id,
            'template_id' => null,
            'input_text' => 'Criar uma API REST em Laravel.',
            'output_text' => 'Prompt gerado.',
        ]);
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
