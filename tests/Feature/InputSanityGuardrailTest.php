<?php

use App\Exceptions\InputUnprocessableException;
use App\Models\Architecture;
use App\Models\Framework;
use App\Models\Language;
use App\Models\Template;
use App\Models\User;
use App\Services\Guardrails\InputSanityGuardrail;
use App\Services\PromptBuilderService;
use App\Services\PromptPipelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->usuario = User::factory()->create();
    templateClassificado();
    Template::query()->create([
        'nome' => 'Desenvolvimento de Módulo / Feature',
        'corpo_template' => 'Módulo: {user_input}',
        'versao' => '1',
        'is_active' => true,
        'is_generic' => true,
        'intent_type' => 'feature',
    ]);
});

it('rejeita entrada lixo com mensagem de validação', function () {
    $resposta = $this->actingAs($this->usuario)
        ->postJson(route('prompts.generate'), ['intencao' => 'gfsarfdgawS']);

    $resposta->assertUnprocessable();
    $resposta->assertJsonValidationErrors([
        'intencao' => InputUnprocessableException::MESSAGE,
    ]);
    $this->assertDatabaseCount('prompts', 0);
});

it('rejeita frase desconexa como pato preto', function () {
    $resposta = $this->actingAs($this->usuario)
        ->postJson(route('prompts.generate'), ['intencao' => 'pato preto']);

    $resposta->assertUnprocessable();
    $resposta->assertJsonValidationErrors([
        'intencao' => InputUnprocessableException::MESSAGE,
    ]);
});

it('gera prompt lean e curto para pedido vago', function () {
    $resultado = app(PromptPipelineService::class)->generate('erro 500 no login');
    $linhas = preg_split('/\R/u', trim($resultado->prompt)) ?: [];

    expect($resultado->prompt)->toStartWith(PromptBuilderService::SECTION_ROLE)
        ->and($resultado->prompt)->toContain('erro 500 no login')
        ->and($resultado->prompt)->not->toContain('S3')
        ->and($resultado->prompt)->not->toContain('Queues')
        ->and($resultado->prompt)->not->toContain('contratos HTTP')
        ->and($resultado->prompt)->not->toContain('Regra de negócio principal')
        ->and(count($linhas))->toBeLessThanOrEqual(InputSanityGuardrail::LEAN_MAX_LINES)
        ->and(count($linhas))->toBeGreaterThanOrEqual(10);
});

it('gera prompt estruturado para entrada válida complexa', function () {
    $pedido = 'Criar uma API REST em Laravel com upload S3, Queues e o endpoint POST /uploads.';
    $resultado = app(PromptPipelineService::class)->generate($pedido);
    $linhas = preg_split('/\R/u', trim($resultado->prompt)) ?: [];

    expect($resultado->prompt)->toStartWith(PromptBuilderService::SECTION_ROLE)
        ->and($resultado->prompt)->toContain($pedido)
        ->and($resultado->prompt)->toContain('S3')
        ->and($resultado->prompt)->toContain('Queues')
        ->and($resultado->prompt)->toContain(PromptBuilderService::SECTION_CONSTRAINTS)
        ->and($resultado->prompt)->toContain(PromptBuilderService::SECTION_SCHEMA)
        ->and($resultado->prompt)->toContain('Regra de negócio')
        ->and(count($linhas))->toBeGreaterThan(InputSanityGuardrail::LEAN_MAX_LINES);
});

function templateClassificado(): Template
{
    $php = Language::query()->create(['nome' => 'PHP', 'slug' => 'php']);
    $laravel = Framework::query()->create([
        'nome' => 'Laravel',
        'slug' => 'laravel',
        'language_id' => $php->id,
    ]);
    $clean = Architecture::query()->create([
        'nome' => 'Clean Architecture',
        'descricao' => 'Camadas independentes de framework.',
    ]);

    $template = Template::query()->create([
        'nome' => 'Template Laravel',
        'corpo_template' => 'Especialista em {technologies}, seguindo {architecture}. Tarefa: {user_input}',
        'versao' => '1',
        'is_active' => true,
    ]);

    $template->languages()->attach($php);
    $template->frameworks()->attach($laravel);
    $template->architectures()->attach($clean);

    return $template;
}
