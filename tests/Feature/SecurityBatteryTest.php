<?php

use App\Models\Prompt;
use App\Models\Template;
use App\Models\User;
use App\Services\AI\Providers\GeminiAIProvider;
use App\Services\Guardrails\SensitiveDataRedactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SecretFixtures;

uses(RefreshDatabase::class);

function bateriaUsuario(): User
{
    return User::factory()->create(['role' => 'USU']);
}

function bateriaAdmin(): User
{
    return User::factory()->create(['role' => 'ADM']);
}

function bateriaPrompt(User $dono): Prompt
{
    $template = Template::query()->create([
        'nome' => 'Feature',
        'corpo_template' => 'Tarefa: {user_input}',
        'versao' => '1',
        'is_active' => true,
    ]);

    return Prompt::query()->create([
        'user_id' => $dono->id,
        'template_id' => $template->id,
        'input_text' => 'Criar cadastro',
        'output_text' => 'Prompt gerado.',
    ]);
}

it('login invalido nao revela se o e-mail existe', function () {
    User::factory()->create(['email' => 'existe@gueass.test']);

    $ausente = $this->from(route('login'))->post(route('login.attempt'), [
        'email' => 'naoexiste@gueass.test',
        'password' => 'SenhaErrada1',
    ]);
    $msgAusente = session('errors')?->first('email');

    $presente = $this->from(route('login'))->post(route('login.attempt'), [
        'email' => 'existe@gueass.test',
        'password' => 'SenhaErrada1',
    ]);
    $msgPresente = session('errors')?->first('email');

    $ausente->assertSessionHasErrors('email');
    $presente->assertSessionHasErrors('email');
    expect($msgAusente)->toBe($msgPresente)->not->toBeNull();
});

it('mass assignment nao promove role nem must_change_password no cadastro', function () {
    $this->post(route('register'), [
        'name' => 'Tentativa',
        'email' => 'mass@gueass.test',
        'password' => 'SenhaForte12',
        'password_confirmation' => 'SenhaForte12',
        'role' => 'ADM',
        'is_admin' => true,
        'must_change_password' => false,
    ])->assertRedirect();

    $user = User::query()->where('email', 'mass@gueass.test')->first();
    expect($user)->not->toBeNull()
        ->and($user->role)->not->toBe('ADM')
        ->and($user->isAdmin())->toBeFalse();
});

it('idor em show feedback destroy e limpeza', function () {
    $dono = bateriaUsuario();
    $intruso = bateriaUsuario();
    $prompt = bateriaPrompt($dono);

    $this->actingAs($intruso)->get(route('prompts.show', $prompt))->assertForbidden();
    $this->actingAs($intruso)->postJson(route('prompts.feedback', $prompt), ['is_useful' => true])->assertForbidden();
    $this->actingAs($intruso)->delete(route('prompts.destroy', $prompt))->assertForbidden();
    $this->actingAs($intruso)->delete(route('prompts.history.clear'))->assertRedirect();

    $this->assertDatabaseHas('prompts', ['id' => $prompt->id, 'user_id' => $dono->id]);
});

it('usuario comum e bloqueado em rotas admin de leitura e escrita', function () {
    $comum = bateriaUsuario();

    $this->actingAs($comum)->get(route('admin.dashboard'))->assertForbidden();
    $this->actingAs($comum)->get(route('admin.audit.index'))->assertForbidden();
    $this->actingAs($comum)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($comum)->post(route('admin.metrics.reset'))->assertForbidden();
    $this->actingAs($comum)->post(route('languages.store'), [
        'nome' => 'Hack',
        'slug' => 'hack-lang',
    ])->assertForbidden();
});

it('csrf das rotas de escrita esta no grupo web', function () {
    foreach ([
        'prompts.history.clear',
        'prompts.generate',
        'admin.metrics.reset',
        'profile.destroy',
        'logout',
    ] as $nome) {
        $rota = app('router')->getRoutes()->getByName($nome);
        expect($rota?->gatherMiddleware())->toContain('web');
    }
});

it('get em rota so de escrita devolve 404 sem revelar allow', function () {
    $this->get('/prompts/historico')->assertRedirect(route('login'));

    $resposta = $this->actingAs(bateriaUsuario())->get('/prompts/historico');
    $resposta->assertNotFound();
    expect($resposta->headers->get('Allow'))->toBeNull()
        ->and($resposta->getContent())->not->toContain('DELETE');
});

it('sql injection nos filtros da auditoria nao quebra nem vaza sql', function () {
    $admin = bateriaAdmin();
    $payload = "admin_language_created' OR 1=1 --";

    $resposta = $this->actingAs($admin)->from(route('admin.audit.index'))->get(route('admin.audit.index', [
        'action' => $payload,
    ]));

    expect(in_array($resposta->status(), [200, 302], true))->toBeTrue()
        ->and($resposta->getContent())
        ->not->toContain('SQLSTATE')
        ->not->toContain('syntax error');
});

it('xss no nome do usuario e escapado no layout', function () {
    $user = User::factory()->create(['name' => '<script>alert(1)</script>']);

    $html = $this->actingAs($user)->get(route('home'))->assertOk()->getContent();

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
});

it('request id com crlf e rejeitado', function () {
    $resposta = $this->get(route('login'), [
        'X-Request-Id' => "abc12345\r\nSet-Cookie: stolen=1",
    ]);

    $resposta->assertOk();
    expect($resposta->headers->get('Set-Cookie') ?? '')->not->toContain('stolen=1')
        ->and((string) $resposta->headers->get('X-Request-Id'))->not->toContain("\r");
});

it('base url do gemini nao e controlavel pelo usuario', function () {
    expect(GeminiAIProvider::DEFAULT_BASE_URL)->toStartWith('https://')
        ->and(file_get_contents(app_path('Services/AI/Providers/GeminiAIProvider.php')))
        ->not->toContain('$request->input')
        ->and(file_get_contents(app_path('Http/Controllers/PromptController.php')))
        ->not->toContain('base_url');
});

it('avatar svg e recusado e o disco local nao e servido', function () {
    Storage::fake('public');
    $user = bateriaUsuario();
    $svg = UploadedFile::fake()->createWithContent('foto.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

    $this->actingAs($user)->postJson(route('profile.avatar'), ['avatar' => $svg])->assertUnprocessable();
    expect(config('filesystems.disks.local.serve'))->toBeFalse();
});

it('intencao acima de 1000 caracteres e recusada antes do guardrail pesado', function () {
    $user = bateriaUsuario();

    $this->actingAs($user)->postJson(route('prompts.generate'), [
        'intencao' => str_repeat('Criar API Laravel ', 80),
    ])->assertUnprocessable()->assertJsonValidationErrors('intencao');
});

it('mais de 20 variables e recusado', function () {
    $user = bateriaUsuario();
    $vars = [];
    for ($i = 0; $i < 21; $i++) {
        $vars['campo_'.$i] = 'valor';
    }

    $this->actingAs($user)->postJson(route('prompts.generate'), [
        'intencao' => 'Criar uma API REST em Laravel com PHP.',
        'variables' => $vars,
    ])->assertUnprocessable()->assertJsonValidationErrors('variables');
});

it('htaccess da raiz nega dotfiles e encaminha para public', function () {
    $htaccess = file_get_contents(base_path('.htaccess'));

    expect($htaccess)->toContain('Require all denied')
        ->and($htaccess)->toContain('public/$1')
        ->and($htaccess)->toContain('Options -Indexes');
});

it('paginas autenticadas nao devolvem hash de senha nem role no json de geracao', function () {
    Template::query()->create([
        'nome' => 'Feature',
        'corpo_template' => 'Tarefa: {user_input}',
        'versao' => '1',
        'is_active' => true,
        'is_generic' => true,
        'intent_type' => 'feature',
    ]);
    $user = bateriaUsuario();

    $resposta = $this->actingAs($user)->postJson(route('prompts.generate'), [
        'intencao' => 'Criar uma API REST em Laravel com PHP seguindo Clean Architecture.',
    ]);

    $resposta->assertCreated();
    $json = $resposta->json();
    expect($json)->not->toHaveKey('password')
        ->and($json)->not->toHaveKey('role')
        ->and(json_encode($json))->not->toContain($user->password);
});

it('segredo e redigido antes de qualquer provedor fake', function () {
    Http::fake();
    $redactor = app(SensitiveDataRedactor::class);
    $inspecao = $redactor->inspect('chave '.SecretFixtures::openaiKey().' no texto');

    expect($inspecao['text'])->toContain('[REDACTED:')
        ->and($inspecao['text'])->not->toContain('sk-');
});
