<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_ve_abas_cards_e_badges_por_bloco(): void
    {
        Template::query()->create([
            'nome' => 'Role-Play (A1)',
            'corpo_template' => 'Corpo A.',
            'versao' => '1',
            'is_active' => true,
            'bloco' => 'A',
        ]);
        Template::query()->create([
            'nome' => 'ReAct (B3)',
            'corpo_template' => 'Corpo B.',
            'versao' => '1',
            'is_active' => true,
            'bloco' => 'B',
        ]);
        Template::query()->create([
            'nome' => 'Code Review (C1)',
            'corpo_template' => 'Corpo C.',
            'versao' => '1',
            'is_active' => true,
            'bloco' => 'C',
        ]);

        $resposta = $this->actingAs(User::factory()->create(['role' => 'ADM']))
            ->get(route('templates.index'));

        $resposta->assertOk();
        $resposta->assertSee('Features / Funcionalidades', false);
        $resposta->assertSee('Raciocínio / Lógica', false);
        $resposta->assertSee('Análise / Etapa 0', false);
        $resposta->assertSee('<article class="catalog-card">', false);
        $resposta->assertSee('catalog-grid grid grid-cols-1 md:grid-cols-3 gap-4', false);
        $resposta->assertSee('btn-catalog-edit', false);
        $resposta->assertSee('btn-catalog-delete', false);
        $resposta->assertDontSee('btn-dark', false);
        $resposta->assertDontSee('catalog-table', false);
        $resposta->assertSee('bg-indigo-100', false);
        $resposta->assertSee('bg-amber-100', false);
        $resposta->assertSee('bg-teal-100', false);
        $resposta->assertSee('Role-Play (A1)');
        $resposta->assertSee('ReAct (B3)');
        $resposta->assertSee('Code Review (C1)');
    }

    public function test_usuario_comum_ve_cards_com_badges(): void
    {
        Template::query()->create([
            'nome' => 'ICCE (A2)',
            'corpo_template' => 'Corpo.',
            'versao' => '1',
            'is_active' => true,
            'bloco' => 'A',
        ]);

        $resposta = $this->actingAs(User::factory()->create(['role' => 'USU']))
            ->get(route('templates.index'));

        $resposta->assertOk();
        $resposta->assertSee('<article class="catalog-card">', false);
        $resposta->assertSee('Features', false);
        $resposta->assertSee('Selecionar', false);
        $resposta->assertDontSee('catalog-table', false);
        $resposta->assertDontSee('btn-dark', false);
    }

    public function test_admin_pode_salvar_template_com_bloco(): void
    {
        $resposta = $this->actingAs(User::factory()->create(['role' => 'ADM']))
            ->post(route('templates.store'), [
                'nome' => 'Novo de análise',
                'corpo_template' => 'Use {user_input}.',
                'versao' => '1',
                'bloco' => 'C',
                'is_active' => '1',
            ]);

        $resposta->assertRedirect(route('templates.index'));
        $this->assertDatabaseHas('templates', [
            'nome' => 'Novo de análise',
            'bloco' => 'C',
        ]);
    }
}
