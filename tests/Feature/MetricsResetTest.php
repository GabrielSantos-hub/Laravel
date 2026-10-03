<?php

use App\Models\AppSetting;
use App\Models\Architecture;
use App\Models\Framework;
use App\Models\Language;
use App\Models\Prompt;
use App\Models\Template;
use App\Models\User;
use App\Services\PromptMetricsService;
use App\Services\Security\SecurityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

afterEach(function () {
    Carbon::setTestNow();
});

function adminDasMetricas(): User
{
    return User::factory()->create(['role' => 'ADM']);
}

function templateDasMetricas(string $nome = 'CRUD'): Template
{
    return Template::query()->create([
        'nome' => $nome,
        'corpo_template' => 'Tarefa: {user_input}',
        'versao' => '1',
        'is_active' => true,
    ]);
}

it('o corte filtra totais satisfacao templates e stacks sem apagar prompts', function () {
    $php = Language::query()->create(['nome' => 'PHP', 'slug' => 'php']);
    $laravel = Framework::query()->create(['nome' => 'Laravel', 'slug' => 'laravel', 'language_id' => $php->id]);
    $clean = Architecture::query()->create(['nome' => 'Clean Architecture', 'descricao' => 'camadas']);
    $crud = templateDasMetricas('CRUD');

    $antigo = Prompt::query()->create([
        'user_id' => User::factory()->create()->id,
        'template_id' => $crud->id,
        'language_id' => $php->id,
        'framework_id' => $laravel->id,
        'architecture_id' => $clean->id,
        'input_text' => 'Antigo',
        'output_text' => 'Prompt antigo.',
        'is_useful' => true,
    ]);
    Prompt::query()->whereKey($antigo->id)->update(['created_at' => '2026-01-01 10:00:00']);

    Carbon::setTestNow('2026-10-03 12:00:00');
    $admin = adminDasMetricas();
    $this->actingAs($admin)->post(route('admin.metrics.reset'))->assertRedirect(route('admin.dashboard'));
    Carbon::setTestNow('2026-10-03 12:00:01');

    $novo = Prompt::query()->create([
        'user_id' => User::factory()->create()->id,
        'template_id' => $crud->id,
        'language_id' => $php->id,
        'framework_id' => $laravel->id,
        'architecture_id' => $clean->id,
        'input_text' => 'Novo',
        'output_text' => 'Prompt novo.',
        'is_useful' => false,
    ]);

    $metricas = app(PromptMetricsService::class)->summary();

    expect($metricas['total_prompts'])->toBe(1)
        ->and($metricas['uteis'])->toBe(0)
        ->and($metricas['nao_uteis'])->toBe(1)
        ->and($metricas['avaliados'])->toBe(1)
        ->and($metricas['satisfacao'])->toBe(0.0)
        ->and($metricas['templates'][0]['total'] ?? null)->toBe(1)
        ->and($metricas['top_stack']['total'] ?? null)->toBe(1)
        ->and($metricas['metrics_since_label'])->not->toBeNull();

    $this->assertDatabaseHas('prompts', ['id' => $antigo->id]);
    $this->assertDatabaseHas('prompts', ['id' => $novo->id]);
});

it('sem corte o painel volta ao historico completo', function () {
    $template = templateDasMetricas();
    $antigo = Prompt::query()->create([
        'user_id' => User::factory()->create()->id,
        'template_id' => $template->id,
        'input_text' => 'Antigo',
        'output_text' => 'Prompt.',
    ]);
    Prompt::query()->whereKey($antigo->id)->update(['created_at' => '2026-01-01 10:00:00']);

    Carbon::setTestNow('2026-10-03 12:00:00');
    $admin = adminDasMetricas();
    $this->actingAs($admin)->post(route('admin.metrics.reset'))->assertRedirect();
    expect(app(PromptMetricsService::class)->summary()['total_prompts'])->toBe(0);

    $this->actingAs($admin)->delete(route('admin.metrics.reset.clear'))->assertRedirect(route('admin.dashboard'));

    expect(app(PromptMetricsService::class)->summary()['total_prompts'])->toBe(1)
        ->and(AppSetting::getValue(PromptMetricsService::RESET_KEY))->toBeNull();
});

it('usuario comum recebe 403 ao zerar metricas', function () {
    $this->actingAs(User::factory()->create(['role' => 'USU']))
        ->post(route('admin.metrics.reset'))
        ->assertForbidden();

    $this->actingAs(User::factory()->create(['role' => 'USU']))
        ->delete(route('admin.metrics.reset.clear'))
        ->assertForbidden();

    expect(AppSetting::getValue(PromptMetricsService::RESET_KEY))->toBeNull();
});

it('registra auditoria e evento ao zerar e ao limpar o corte', function () {
    $spy = Mockery::mock(Psr\Log\LoggerInterface::class);
    $spy->shouldReceive('info')->once()->withArgs(fn (string $event): bool => $event === 'admin_metrics_reset');
    $spy->shouldReceive('info')->once()->withArgs(fn (string $event): bool => $event === 'admin_metrics_reset_cleared');
    $this->app->instance(SecurityLogger::class, new SecurityLogger($spy));

    $admin = adminDasMetricas();
    $this->actingAs($admin)->post(route('admin.metrics.reset'))->assertRedirect();
    $this->actingAs($admin)->delete(route('admin.metrics.reset.clear'))->assertRedirect();

    $this->assertDatabaseHas('audit_logs', ['action' => 'admin_metrics_reset', 'user_id' => $admin->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'admin_metrics_reset_cleared', 'user_id' => $admin->id]);
});

it('o painel mostra a data de corte e o botao de restaurar', function () {
    $admin = adminDasMetricas();
    $this->actingAs($admin)->post(route('admin.metrics.reset'));

    $html = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->getContent();

    expect($html)->toContain('Métricas consideradas desde')
        ->and($html)->toContain('aria-label="Zerar métricas"')
        ->and($html)->toContain('title="Zerar métricas"')
        ->and($html)->toContain('fa-arrows-rotate')
        ->and($html)->toContain('aria-hidden="true"')
        ->and($html)->toContain('Considerar todo o histórico')
        ->and($html)->not->toContain('onclick=');
});
