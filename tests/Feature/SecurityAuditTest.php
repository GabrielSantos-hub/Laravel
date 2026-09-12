<?php

use App\Exceptions\InputUnprocessableException;
use App\Http\Controllers\PromptController;
use App\Models\Architecture;
use App\Models\Framework;
use App\Models\Language;
use App\Models\Prompt;
use App\Models\Template;
use App\Models\User;
use App\Services\PromptPipelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    $this->usuario = User::factory()->create();
    catalogoParaAuditoriaDeSeguranca();
});

it('rejeita tentativas de xss nos campos de texto da geracao', function (string $payload) {
    $resposta = $this->actingAs($this->usuario)
        ->postJson(route('prompts.generate'), ['intencao' => $payload]);

    $resposta->assertUnprocessable();
    $resposta->assertJsonValidationErrors([
        'intencao' => InputUnprocessableException::MESSAGE,
    ]);
    $resposta->assertDontSee('<script>', false);
    $resposta->assertDontSee('alert(1)', false);
    expect(Prompt::query()->count())->toBe(0);
})->with([
    'Criar API Laravel <script>alert(1)</script>',
    'Criar API Laravel <img src=x onerror=alert(1)>',
    '<script>alert(1)</script> criar endpoint Laravel',
]);

it('escapa payload xss ao renderizar o historico em blade', function () {
    $prompt = Prompt::query()->create([
        'user_id' => $this->usuario->id,
        'input_text' => '<script>alert(1)</script>',
        'output_text' => '<img src=x onerror=alert(1)>',
    ]);

    $html = $this->actingAs($this->usuario)
        ->get(route('prompts.show', $prompt))
        ->assertOk()
        ->getContent();

    expect($html)
        ->not->toContain('<script>alert(1)</script>')
        ->not->toContain('<img src=x onerror=alert(1)>')
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->toContain('&lt;img src=x onerror=alert(1)&gt;');
});

it('rejeita payloads com tentativa de sql injection', function (string $payload) {
    $resposta = $this->actingAs($this->usuario)
        ->postJson(route('prompts.generate'), ['intencao' => $payload]);

    $resposta->assertUnprocessable();
    $resposta->assertJsonValidationErrors(['intencao']);
    $resposta->assertDontSee('SQLSTATE', false);
    $resposta->assertDontSee('DROP TABLE', false);
    expect(Prompt::query()->count())->toBe(0);
})->with([
    "Criar API Laravel' OR 1=1 --",
    "Criar API Laravel'; DROP TABLE users; --",
    'Criar API Laravel UNION SELECT password FROM users',
]);

it('retorna 429 quando a geracao excede o rate limit', function () {
    $intencao = ['intencao' => 'Criar uma API REST em Laravel com PHP.'];

    for ($tentativa = 1; $tentativa <= 10; $tentativa++) {
        $this->actingAs($this->usuario)
            ->postJson(route('prompts.generate'), $intencao)
            ->assertCreated();
    }

    $this->actingAs($this->usuario)
        ->postJson(route('prompts.generate'), $intencao)
        ->assertStatus(429)
        ->assertHeader('Retry-After');

    expect(Prompt::query()->count())->toBe(10);
});

it('devolve resposta generica sem stack trace em erro inesperado', function () {
    config(['app.debug' => true]);

    $this->mock(PromptPipelineService::class, function (MockInterface $mock) {
        $mock->shouldReceive('generate')->andThrow(new \RuntimeException(
            'SQLSTATE[HY000] at C:\\laragon\\www\\Laravel\\app\\Services\\PromptGeneratorService.php:117'
        ));
    });

    $resposta = $this->actingAs($this->usuario)
        ->postJson(route('prompts.generate'), [
            'intencao' => 'Criar uma API REST em Laravel com PHP.',
        ]);

    $resposta->assertStatus(500);
    $resposta->assertExactJson([
        'message' => PromptController::GENERIC_FAILURE_MESSAGE,
    ]);
    $resposta->assertDontSee('PromptGeneratorService.php', false);
    $resposta->assertDontSee('C:\\laragon', false);
    $resposta->assertDontSee('SQLSTATE', false);
    $resposta->assertJsonMissingPath('file');
    $resposta->assertJsonMissingPath('trace');
    $resposta->assertJsonMissingPath('exception');
    expect(Prompt::query()->count())->toBe(0);
});

function catalogoParaAuditoriaDeSeguranca(): Template
{
    $php = Language::query()->create(['nome' => 'PHP', 'slug' => 'php-security-audit']);
    $laravel = Framework::query()->create([
        'nome' => 'Laravel',
        'slug' => 'laravel-security-audit',
        'language_id' => $php->id,
    ]);
    $clean = Architecture::query()->create([
        'nome' => 'Clean Architecture',
        'descricao' => 'Camadas independentes de framework.',
    ]);

    $template = Template::query()->create([
        'nome' => 'Template Laravel Auditoria',
        'corpo_template' => 'Especialista em {technologies}. Tarefa: {user_input}',
        'versao' => '1',
        'is_active' => true,
        'is_generic' => true,
    ]);

    $template->languages()->attach($php);
    $template->frameworks()->attach($laravel);
    $template->architectures()->attach($clean);

    return $template;
}
