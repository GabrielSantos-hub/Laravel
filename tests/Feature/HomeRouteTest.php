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
        $response->assertViewHas(['architectures', 'languages', 'frameworks', 'templates']);
    }

    public function test_a_tela_de_geracao_oferece_apenas_templates_ativos(): void
    {
        Template::query()->create([
            'nome' => 'Template ativo',
            'corpo_template' => 'Corpo.',
            'versao' => '1',
            'is_active' => true,
        ]);

        Template::query()->create([
            'nome' => 'Template arquivado',
            'corpo_template' => 'Corpo.',
            'versao' => '1',
            'is_active' => false,
        ]);

        $response = $this->actingAs(User::factory()->create())->get('/');

        $response->assertOk();
        $this->assertSame(
            ['Template ativo'],
            $response->viewData('templates')->pluck('nome')->all()
        );
    }
}
