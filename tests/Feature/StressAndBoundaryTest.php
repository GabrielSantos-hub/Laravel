<?php

use App\Exceptions\InputUnprocessableException;
use App\Models\Architecture;
use App\Models\Framework;
use App\Models\Language;
use App\Models\Prompt;
use App\Models\Template;
use App\Models\User;
use App\Services\Guardrails\InputSanityGuardrail;
use App\Services\PromptBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->usuario = User::factory()->create();
    stressTemplateCatalogo();
});

dataset('gibberish', [
    'gfsarfdgawS',
    'asdfghjkl123',
    'zzxczxcqwe',
]);

dataset('fora_de_escopo', [
    'pato preto num tira de freixo',
    'receita de bolo de cenoura',
]);

dataset('pedidos_lean', [
    'erro 500 no login',
    'criar seeder de usuários',
]);

it('rejeita gibberish com HTTP 422 e a mensagem do guardrail', function (string $lixo) {
    $resposta = $this->actingAs($this->usuario)
        ->postJson(route('prompts.generate'), ['intencao' => $lixo]);

    $resposta->assertStatus(422);
    $resposta->assertJsonValidationErrors([
        'intencao' => InputUnprocessableException::MESSAGE,
    ]);
    expect(Prompt::query()->count())->toBe(0);
})->with('gibberish');

it('rejeita frases fora de escopo com HTTP 422 e a mensagem do guardrail', function (string $frase) {
    $resposta = $this->actingAs($this->usuario)
        ->postJson(route('prompts.generate'), ['intencao' => $frase]);

    $resposta->assertStatus(422);
    $resposta->assertJsonValidationErrors([
        'intencao' => InputUnprocessableException::MESSAGE,
    ]);
    expect(Prompt::query()->count())->toBe(0);
})->with('fora_de_escopo');

it('gera prompt lean e proporcional para pedidos curtos validados', function (string $pedido) {
    $resposta = $this->actingAs($this->usuario)
        ->from(route('home'))
        ->followingRedirects()
        ->post(route('prompts.generate'), ['intencao' => $pedido]);

    $resposta->assertOk();
    expect(Prompt::query()->count())->toBe(1);

    $prompt = (string) Prompt::query()->sole()->output_text;
    $linhas = preg_split('/\R/u', trim($prompt)) ?: [];

    expect($prompt)->toStartWith(PromptBuilderService::SECTION_ROLE)
        ->and($prompt)->toContain($pedido)
        ->and($prompt)->not->toContain('S3')
        ->and($prompt)->not->toContain('Queues')
        ->and($prompt)->not->toContain('contratos HTTP')
        ->and($prompt)->not->toContain('Regra de negócio principal')
        ->and(count($linhas))->toBeLessThanOrEqual(InputSanityGuardrail::LEAN_MAX_LINES);
})->with('pedidos_lean');

it('gera especificação em prosa sem instruções de código', function () {
    $pedido = 'Elabore a especificação técnica do 2FA em prosa. Não quero código PHP.';

    $resposta = $this->actingAs($this->usuario)
        ->from(route('home'))
        ->followingRedirects()
        ->post(route('prompts.generate'), ['intencao' => $pedido]);

    $resposta->assertOk();

    $prompt = (string) Prompt::query()->sole()->output_text;

    expect($prompt)->toStartWith(PromptBuilderService::SECTION_ROLE)
        ->and($prompt)->toContain($pedido)
        ->and($prompt)->toContain(PromptBuilderService::NO_CODE_CONSTRAINT)
        ->and($prompt)->not->toContain('Escreva o código')
        ->and($prompt)->not->toContain('escreva o código')
        ->and($prompt)->not->toMatch('/```/')
        ->and($prompt)->not->toContain('blocos de código');
});

it('gera envelope profissional completo para o caso complexo', function () {
    $pedido = 'Criar uma API REST em Laravel com upload S3, filas Queues e envio de Mail para notificar o usuário.';

    $resposta = $this->actingAs($this->usuario)
        ->from(route('home'))
        ->followingRedirects()
        ->post(route('prompts.generate'), ['intencao' => $pedido]);

    $resposta->assertOk();

    $prompt = (string) Prompt::query()->sole()->output_text;
    $linhas = preg_split('/\R/u', trim($prompt)) ?: [];

    expect($prompt)->toStartWith(PromptBuilderService::SECTION_ROLE)
        ->and($prompt)->toContain($pedido)
        ->and($prompt)->toContain('Laravel')
        ->and($prompt)->toContain('S3')
        ->and($prompt)->toContain('Queues')
        ->and($prompt)->toContain('Mail')
        ->and($prompt)->toContain(PromptBuilderService::SECTION_TASK)
        ->and($prompt)->toContain(PromptBuilderService::SECTION_CONSTRAINTS)
        ->and($prompt)->toContain(PromptBuilderService::SECTION_SCHEMA)
        ->and($prompt)->toContain(PromptBuilderService::SECTION_VALIDATION)
        ->and($prompt)->toContain('Regra de negócio')
        ->and(count($linhas))->toBeGreaterThan(InputSanityGuardrail::LEAN_MAX_LINES);
});

function stressTemplateCatalogo(): Template
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

    Template::query()->create([
        'nome' => 'Desenvolvimento de Módulo / Feature',
        'corpo_template' => 'Módulo: {user_input}',
        'versao' => '1',
        'is_active' => true,
        'is_generic' => true,
        'intent_type' => 'feature',
    ]);

    return $template;
}
