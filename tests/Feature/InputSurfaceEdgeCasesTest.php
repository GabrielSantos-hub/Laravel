<?php

use App\Models\Architecture;
use App\Models\Language;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

it('login vazio e tipo errado nao geram 500', function () {
    $vazio = $this->from('/login')->post('/login', [
        'email' => '',
        'password' => '',
    ]);
    $vazio->assertRedirect('/login');
    $vazio->assertSessionHasErrors('email');
    expect($vazio->status())->not->toBe(500);

    $tipo = $this->from('/login')->postJson('/login', [
        'email' => ['x'],
        'password' => ['y'],
    ]);
    $tipo->assertUnprocessable();
    $tipo->assertJsonStructure(['message', 'errors']);
});

it('cadastro rejeita nome longo e senha fraca sem stack', function () {
    $resposta = $this->from('/login')->post('/register', [
        'name' => str_repeat('n', 256),
        'email' => 'novo@example.test',
        'password' => 'fraca',
        'password_confirmation' => 'fraca',
    ]);
    $resposta->assertRedirect('/login');
    $resposta->assertSessionHasErrors(['name', 'password']);
    $resposta->assertSessionHasInput('name');
    expect(User::query()->where('email', 'novo@example.test')->exists())->toBeFalse();
});

it('perfil rejeita nome vazio e preserva o valor invalido', function () {
    $usuario = User::factory()->create(['name' => 'Ana']);
    $resposta = $this->actingAs($usuario)
        ->from(route('profile.edit'))
        ->put(route('profile.update'), [
            'name' => '   ',
        ]);
    $resposta->assertRedirect(route('profile.edit'));
    $resposta->assertSessionHasErrors('name');
    expect($usuario->fresh()->name)->toBe('Ana');
});

it('feedback exige booleano e destroy so do dono', function () {
    $dono = User::factory()->create();
    $outro = User::factory()->create();
    $prompt = Prompt::query()->create([
        'user_id' => $dono->id,
        'input_text' => 'Pedido Laravel',
        'output_text' => 'Envelope',
    ]);

    $this->actingAs($dono)
        ->postJson(route('prompts.feedback', $prompt), ['is_useful' => 'talvez'])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('is_useful');

    $this->actingAs($outro)
        ->delete(route('prompts.destroy', $prompt))
        ->assertForbidden();
});

it('api de frameworks da linguagem inexistente devolve 404 sem stack', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/api/languages/999999/frameworks')
        ->assertNotFound()
        ->assertDontSee('Stack trace', false);
});

it('admin rejeita slug invalido e descricao longa demais', function () {
    $admin = User::factory()->create(['role' => 'ADM']);

    $slug = $this->actingAs($admin)
        ->from(route('languages.create'))
        ->post(route('languages.store'), [
            'nome' => 'Linguagem Teste',
            'slug' => 'php com espaço!',
        ]);
    $slug->assertRedirect(route('languages.create'));
    $slug->assertSessionHasErrors('slug');
    $slug->assertSessionHasInput('nome', 'Linguagem Teste');

    $desc = $this->actingAs($admin)
        ->from(route('architectures.create'))
        ->post(route('architectures.store'), [
            'nome' => 'Hex Extra',
            'descricao' => str_repeat('d', 5001),
        ]);
    $desc->assertRedirect(route('architectures.create'));
    $desc->assertSessionHasErrors('descricao');
});

it('auditoria rejeita data invalida com 422 e nao 500', function () {
    $admin = User::factory()->create(['role' => 'ADM']);

    $resposta = $this->actingAs($admin)->getJson(route('admin.audit.index', [
        'from' => 'ontem',
        'action' => 'admin_language_created',
    ]));

    $resposta->assertUnprocessable();
    $resposta->assertJsonValidationErrorFor('from');
    expect($resposta->status())->not->toBe(500);
});
