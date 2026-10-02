<?php

use App\Services\Guardrails\SensitiveDataRedactor;

$redactor = new SensitiveDataRedactor;

it('substitui chave aws', function () use ($redactor) {
    $saida = $redactor->redact('Usar a chave AKIAIOSFODNN7EXAMPLE no SDK.');

    expect($saida)->toContain('[REDACTED:aws_access_key]')
        ->and($saida)->not->toContain('AKIAIOSFODNN7EXAMPLE');
});

it('substitui token github', function () use ($redactor) {
    $token = 'ghp_abcdefghijklmnopqrstuvwxyz0123456789';
    $saida = $redactor->redact("Token {$token} no CI.");

    expect($saida)->toContain('[REDACTED:github_token]')
        ->and($saida)->not->toContain($token);
});

it('substitui authorization bearer e par password', function () use ($redactor) {
    $saida = $redactor->redact("Authorization: Bearer super-secreto-token\npassword=SuperSegredo123");

    expect($saida)->toContain('[REDACTED:bearer]')
        ->and($saida)->toContain('[REDACTED:password_pair]')
        ->and($saida)->not->toContain('super-secreto-token')
        ->and($saida)->not->toContain('SuperSegredo123');
});

it('substitui string de conexao com senha', function () use ($redactor) {
    $saida = $redactor->redact('mysql://gueass:s3nh4@127.0.0.1:3308/gueass_db');

    expect($saida)->toContain('[REDACTED:connection_string]')
        ->and($saida)->not->toContain('s3nh4');
});

it('nao altera pedido sem segredo', function () use ($redactor) {
    $pedido = 'Criar uma API REST em Laravel com autenticação Sanctum.';

    expect($redactor->redact($pedido))->toBe($pedido);
});

it('redige mapa de variaveis', function () use ($redactor) {
    $mapa = $redactor->redactMap([
        'NOME' => 'Cliente',
        'CHAVE' => 'AKIAIOSFODNN7EXAMPLE',
    ]);

    expect($mapa['NOME'])->toBe('Cliente')
        ->and($mapa['CHAVE'])->toBe('[REDACTED:aws_access_key]');
});
