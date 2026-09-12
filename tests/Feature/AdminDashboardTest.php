<?php

namespace Tests\Feature;

use App\Models\Framework;
use App\Models\Language;
use App\Models\Prompt;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_e_redirecionado_para_o_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_usuario_comum_nao_acessa_o_painel(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'USU']))
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_admin_acessa_o_painel(): void
    {
        $resposta = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $resposta->assertOk();
        $resposta->assertViewIs('admin.dashboard');
        $resposta->assertSee('Painel de métricas');
        $resposta->assertSee('grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4', false);
    }

    public function test_a_rota_admin_leva_ao_painel(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_o_painel_consolida_totais_satisfacao_e_rankings(): void
    {
        $php = Language::query()->create(['nome' => 'PHP', 'slug' => 'php']);
        $python = Language::query()->create(['nome' => 'Python', 'slug' => 'python']);
        $laravel = Framework::query()->create(['nome' => 'Laravel', 'slug' => 'laravel', 'language_id' => $php->id]);

        $crud = $this->template('CRUD');
        $analise = $this->template('Análise');

        // 3 prompts em PHP/Laravel/CRUD, sendo 2 avaliados como úteis.
        $this->prompt($crud, $php, $laravel, true);
        $this->prompt($crud, $php, $laravel, true);
        $this->prompt($crud, $php, $laravel, false);
        $this->prompt($analise, $python, null, null);

        $metricas = $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->viewData('metricas');

        $this->assertSame(4, $metricas['total_prompts']);
        $this->assertSame(2, $metricas['uteis']);
        $this->assertSame(1, $metricas['nao_uteis']);
        $this->assertSame(3, $metricas['avaliados']);
        $this->assertSame(66.7, $metricas['satisfacao']);

        $this->assertSame(['rotulo' => 'CRUD', 'total' => 3], $metricas['top_template']);
        $this->assertSame(
            [['rotulo' => 'CRUD', 'total' => 3], ['rotulo' => 'Análise', 'total' => 1]],
            $metricas['templates']
        );

        // Linguagens e frameworks disputam o mesmo ranking; empate no total é
        // desempatado pelo rótulo, então Laravel vem antes de PHP.
        $this->assertSame(['rotulo' => 'Laravel', 'total' => 3, 'tipo' => 'framework'], $metricas['top_stack']);
        $this->assertSame(
            [
                ['rotulo' => 'Laravel', 'total' => 3, 'tipo' => 'framework'],
                ['rotulo' => 'PHP', 'total' => 3, 'tipo' => 'linguagem'],
                ['rotulo' => 'Python', 'total' => 1, 'tipo' => 'linguagem'],
            ],
            $metricas['stacks']
        );
    }

    public function test_sem_avaliacoes_o_indice_de_satisfacao_fica_indefinido(): void
    {
        $this->prompt($this->template('CRUD'), null, null, null);

        $metricas = $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->viewData('metricas');

        $this->assertNull($metricas['satisfacao']);
        $this->assertSame(0, $metricas['avaliados']);
    }

    public function test_painel_vazio_mostra_os_estados_sem_dados_em_vez_de_graficos(): void
    {
        $resposta = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $resposta->assertOk();
        $resposta->assertSee('Sem avaliações registradas até o momento.');
        $resposta->assertSee('Nenhum template utilizado ainda.');
        $resposta->assertSee('Nenhuma linguagem ou framework informado ainda.');
        $resposta->assertDontSee('id="grafico-satisfacao"', false);
    }

    public function test_os_graficos_usam_barras_horizontais(): void
    {
        $this->prompt($this->template('CRUD'), null, null, true);

        $resposta = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $resposta->assertOk();
        $resposta->assertSee("indexAxis: 'y'", false);
        $resposta->assertSee('id="grafico-satisfacao"', false);
        $resposta->assertSee('id="grafico-templates"', false);
    }

    public function test_o_menu_lateral_so_mostra_o_painel_para_o_admin(): void
    {
        $this->actingAs($this->admin())
            ->get(route('home'))
            ->assertSee('Painel de métricas')
            ->assertSee('Usuários')
            ->assertDontSee('Administração');

        $this->actingAs(User::factory()->create(['role' => 'USU']))
            ->get(route('home'))
            ->assertDontSee('Painel de métricas')
            ->assertDontSee('>Usuários</span>', false);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'ADM']);
    }

    private function template(string $nome): Template
    {
        return Template::query()->create([
            'nome' => $nome,
            'corpo_template' => 'Tarefa: {user_input}',
            'versao' => '1',
            'is_active' => true,
        ]);
    }

    private function prompt(Template $template, ?Language $language, ?Framework $framework, ?bool $isUseful): Prompt
    {
        return Prompt::query()->create([
            'user_id' => User::factory()->create()->id,
            'template_id' => $template->id,
            'language_id' => $language?->id,
            'framework_id' => $framework?->id,
            'input_text' => 'Criar um cadastro.',
            'output_text' => 'Prompt gerado.',
            'is_useful' => $isUseful,
        ]);
    }
}
