<?php

use App\Exceptions\InputUnprocessableException;
use App\Services\Guardrails\InputSanityGuardrail;

it('rejeita batida aleatória de teclado', function (string $lixo) {
    $guardrail = new InputSanityGuardrail;

    expect($guardrail->assess($lixo)->accepted)->toBeFalse()
        ->and(fn () => $guardrail->assertSane($lixo))
        ->toThrow(InputUnprocessableException::class, InputUnprocessableException::MESSAGE);
})->with([
    'gfsarfdgawS',
    'asdfghjkl',
    'lkjhgfds',
    'qwertyuiopzx',
]);

it('rejeita frases desconexas sem escopo de software', function (string $frase) {
    expect((new InputSanityGuardrail)->assess($frase)->accepted)->toBeFalse();
})->with([
    'pato preto',
    'papo rato padeiro',
    'hoje o dia está bonito',
    'banana com manteiga',
]);

it('classifica pedido curto e vago como lean', function (string $pedido) {
    $verdict = (new InputSanityGuardrail)->assess($pedido);

    expect($verdict->accepted)->toBeTrue()
        ->and($verdict->lean)->toBeTrue();
})->with([
    'erro 500 no login',
    'criar seeder de usuários',
]);

it('classifica pedido complexo como envelope completo', function () {
    $verdict = (new InputSanityGuardrail)->assess(
        'Criar uma API REST em Laravel com upload S3, Queues e o endpoint POST /uploads.'
    );

    expect($verdict->accepted)->toBeTrue()
        ->and($verdict->lean)->toBeFalse();
});

it('rejeita payloads de xss e sql injection mesmo com escopo de software', function (string $payload) {
    $guardrail = new InputSanityGuardrail;

    expect($guardrail->isMalicious($payload))->toBeTrue()
        ->and($guardrail->assess($payload)->accepted)->toBeFalse()
        ->and(fn () => $guardrail->assertSane($payload))
        ->toThrow(InputUnprocessableException::class, InputUnprocessableException::MESSAGE);
})->with([
    'Criar API Laravel <script>alert(1)</script>',
    'Criar API Laravel <img src=x onerror=alert(1)>',
    "Criar API Laravel' OR 1=1 --",
    "Criar API Laravel'; DROP TABLE users; --",
    'Criar API Laravel UNION SELECT password FROM users',
]);

it('aceita pedidos legitimos sobre xss e sql injection', function (string $pedido) {
    expect((new InputSanityGuardrail)->assess($pedido)->accepted)->toBeTrue()
        ->and((new InputSanityGuardrail)->isMalicious($pedido))->toBeFalse();
})->with([
    'Auditoria de middlewares de rotas contra SQL Injection e XSS',
    'Implementar proteção CSRF e sanitização contra XSS no formulário',
    'Revisar o código dos middlewares contra injeção SQL',
]);
