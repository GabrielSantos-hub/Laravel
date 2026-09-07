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
        $response->assertViewHas(['architectures', 'languages', 'frameworks']);
        $response->assertViewMissing('templates');
    }

    public function test_a_tela_de_geracao_nao_oferece_escolha_de_template(): void
    {
        Template::query()->create([
            'nome' => 'Template ativo',
            'corpo_template' => 'Corpo.',
            'versao' => '1',
            'is_active' => true,
        ]);

        $response = $this->actingAs(User::factory()->create())->get('/');

        $response->assertOk();
        $response->assertDontSee('name="template_id"', false);
        $response->assertDontSee('Template ativo');
    }
}
