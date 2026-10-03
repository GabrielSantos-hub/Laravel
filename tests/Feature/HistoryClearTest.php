<?php

use App\Models\Prompt;
use App\Models\Template;
use App\Models\User;
use App\Services\Security\SecurityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

function templateParaHistorico(): Template
{
    return Template::query()->create([
        'nome' => 'Feature',
        'corpo_template' => 'Tarefa: {user_input}',
        'versao' => '1',
        'is_active' => true,
    ]);
}

function promptDoUsuario(User $usuario, Template $template, string $texto = 'Criar cadastro'): Prompt
{
    return Prompt::query()->create([
        'user_id' => $usuario->id,
        'template_id' => $template->id,
        'input_text' => $texto,
        'output_text' => 'Prompt gerado.',
    ]);
}

it('apaga somente os prompts do dono e ignora ids forjados', function () {
    $template = templateParaHistorico();
    $dono = User::factory()->create();
    $outro = User::factory()->create();
    $meus = [
        promptDoUsuario($dono, $template, 'Primeiro'),
        promptDoUsuario($dono, $template, 'Segundo'),
    ];
    $alheio = promptDoUsuario($outro, $template, 'De outro');

    $resposta = $this->actingAs($dono)->delete(route('prompts.history.clear'), [
        'user_id' => $outro->id,
        'ids' => [$alheio->id],
    ]);

    $resposta->assertRedirect(route('home'));
    $resposta->assertSessionHas('sucesso', '2 prompts removidos');
    $this->assertDatabaseMissing('prompts', ['id' => $meus[0]->id]);
    $this->assertDatabaseMissing('prompts', ['id' => $meus[1]->id]);
    $this->assertDatabaseHas('prompts', ['id' => $alheio->id, 'user_id' => $outro->id]);
});

it('json devolve a contagem sem afetar outro usuario', function () {
    $template = templateParaHistorico();
    $dono = User::factory()->create();
    $outro = User::factory()->create();
    promptDoUsuario($dono, $template);
    promptDoUsuario($outro, $template);

    $this->actingAs($dono)
        ->deleteJson(route('prompts.history.clear'), ['user_id' => $outro->id])
        ->assertOk()
        ->assertJsonPath('removed', 1)
        ->assertJsonPath('message', '1 prompt removido')
        ->assertJsonMissingPath('input_text')
        ->assertJsonMissingPath('output_text');

    $this->assertDatabaseCount('prompts', 1);
    $this->assertDatabaseHas('prompts', ['user_id' => $outro->id]);
});

it('visitante e redirecionado no limpar historico', function () {
    $this->delete(route('prompts.history.clear'))->assertRedirect(route('login'));
});

it('registra history_cleared so com a contagem', function () {
    $spy = Mockery::mock(Psr\Log\LoggerInterface::class);
    $spy->shouldReceive('info')->once()->withArgs(function (string $event, array $payload): bool {
        return $event === 'history_cleared'
            && ($payload['context']['removed'] ?? null) === 2
            && ! array_key_exists('input_text', $payload['context'])
            && ! array_key_exists('output_text', $payload['context'])
            && ! array_key_exists('prompt', $payload['context']);
    });
    $this->app->instance(SecurityLogger::class, new SecurityLogger($spy));

    $template = templateParaHistorico();
    $dono = User::factory()->create();
    promptDoUsuario($dono, $template);
    promptDoUsuario($dono, $template);

    $this->actingAs($dono)->delete(route('prompts.history.clear'))->assertRedirect(route('home'));
});

it('a rota de limpeza tem auth password.changed e throttle', function () {
    $rota = app('router')->getRoutes()->getByName('prompts.history.clear');

    expect($rota)->not->toBeNull()
        ->and($rota->gatherMiddleware())->toContain('web')
        ->and($rota->gatherMiddleware())->toContain('auth')
        ->and($rota->gatherMiddleware())->toContain('password.changed')
        ->and($rota->gatherMiddleware())->toContain('throttle:5,1');
});

it('a pagina renderiza o dialogo com csrf e sem onclick', function () {
    $template = templateParaHistorico();
    $dono = User::factory()->create();
    promptDoUsuario($dono, $template);

    $html = $this->actingAs($dono)->get(route('home'))->assertOk()->getContent();

    expect($html)->toContain('Limpar meu histórico')
        ->and($html)->toContain('Isto apagará todos os seus prompts e não pode ser desfeito.')
        ->and($html)->toContain('name="_token"')
        ->and($html)->toContain('id="clear-history-modal"')
        ->and($html)->not->toContain('onclick=');
});
