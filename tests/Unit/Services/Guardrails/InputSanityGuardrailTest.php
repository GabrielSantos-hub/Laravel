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

it('rejeita lista solta de palavras mesmo com termos de informatica', function (string $frase) {
    expect((new InputSanityGuardrail)->assess($frase)->accepted)->toBeFalse();
})->with([
    'asdf qwer zxcv 12345 teste blablabla computador azul correr janela.',
    'teste computador azul correr janela sistema',
    'computador sistema tecnologia banco dados código programa.',
    'Quero aprender a preparar uma receita de bolo de chocolate.',
    'Hoje está um dia bonito e quero viajar para Santa Catarina.',
    'Quero comer tapioca bem rápido hoje',
]);

it('aceita pedidos legitimos em linguagem natural', function (string $pedido) {
    expect((new InputSanityGuardrail)->assess($pedido)->accepted)->toBeTrue();
})->with([
    'Quero criar um sistema de cadastro de clientes com nome, e-mail e telefone.',
    'Minha aplicação está apresentando erro 500 quando tento fazer login. Preciso investigar e corrigir o problema.',
    'Preciso integrar meu sistema com uma API externa para consultar CEP e preencher automaticamente os endereços.',
    'Quero que apenas administradores possam excluir usuários, enquanto os funcionários podem somente visualizar e cadastrar informações.',
    'Preciso documentar os endpoints da API REST do meu projeto.',
    'Quero melhorar o visual da página inicial do meu site para funcionar corretamente em celulares.',
    'Implementar autenticação JWT Laravel Sanctum',
    'erro 500 login laravel sanctum token',
    'Configure CI pipeline for my FastAPI service',
    'Crear una API REST en Laravel con autenticación Sanctum',
]);

it('nao aceita verbo construir sem contexto de software', function (string $frase) {
    expect((new InputSanityGuardrail)->assess($frase)->accepted)->toBeFalse();
})->with([
    'Quero construir uma casa.',
    'Quero construir uma garagem.',
    'Preciso construir uma mesa de madeira.',
    'Quero aprender a construir uma churrasqueira.',
]);

it('aceita verbo construir com contexto de software', function (string $pedido) {
    expect((new InputSanityGuardrail)->assess($pedido)->accepted)->toBeTrue();
})->with([
    'Quero construir um sistema de cadastro de clientes.',
    'Preciso construir uma aplicação web para controle financeiro.',
    'Quero construir uma API REST em Laravel.',
    'Preciso criar um sistema para gerenciar uma biblioteca.',
    'Quero desenvolver um site responsivo para minha empresa.',
    'Minha aplicação apresenta erro 500 quando tento fazer login. Preciso corrigir.',
]);

it('aceita pedidos legitimos sobre xss e sql injection', function (string $pedido) {
    expect((new InputSanityGuardrail)->assess($pedido)->accepted)->toBeTrue()
        ->and((new InputSanityGuardrail)->isMalicious($pedido))->toBeFalse();
})->with([
    'Auditoria de middlewares de rotas contra SQL Injection e XSS',
    'Implementar proteção CSRF e sanitização contra XSS no formulário',
    'Revisar o código dos middlewares contra injeção SQL',
]);
