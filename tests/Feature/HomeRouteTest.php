<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_e_redirecionado_para_o_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_usuario_autenticado_recebe_a_tela_de_geracao(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/');

        $response->assertOk();
        $response->assertViewIs('prompts.index');
        $response->assertViewHas(['architectures', 'languages', 'frameworks', 'templates', 'selectedTemplate']);
        $this->assertNull($response->viewData('selectedTemplate'));
    }

    public function test_a_tela_de_geracao_oferece_a_escolha_manual_de_template(): void
    {
        Template::query()->create([
            'nome' => 'Template ativo',
            'corpo_template' => 'Corpo.',
            'versao' => '1',
            'is_active' => true,
        ]);

        $response = $this->actingAs(User::factory()->create())->get('/');

        $response->assertOk();
        $response->assertSee('name="template_id"', false);
        $response->assertSee('Deixar a IA escolher o template…', false);
        $response->assertSee('Template ativo');
    }

    public function test_templates_inativos_ficam_fora_da_escolha_manual(): void
    {
        Template::query()->create([
            'nome' => 'Template aposentado',
            'corpo_template' => 'Corpo.',
            'versao' => '1',
            'is_active' => false,
        ]);

        $response = $this->actingAs(User::factory()->create())->get('/');

        $response->assertOk();
        $response->assertDontSee('Template aposentado');
    }
}
