<?php

use App\Services\Guardrails\SensitiveDataRedactor;
use Tests\Support\SecretFixtures;

$redactor = new SensitiveDataRedactor;

it('substitui chave aws', function () use ($redactor) {
    $chave = SecretFixtures::awsAccessKey();
    $saida = $redactor->redact("Usar a chave {$chave} no SDK.");

    expect($saida)->toContain('[REDACTED:aws_access_key]')
        ->and($saida)->not->toContain($chave);
});

it('substitui token github ghp', function () use ($redactor) {
    $token = SecretFixtures::githubClassic();
    $saida = $redactor->redact("Token {$token} no CI.");

    expect($saida)->toContain('[REDACTED:github_token]')
        ->and($saida)->not->toContain($token);
});

it('substitui token github_pat', function () use ($redactor) {
    $token = SecretFixtures::githubFineGrained();
    $saida = $redactor->redact("PAT {$token} no deploy.");

    expect($saida)->toContain('[REDACTED:github_token]')
        ->and($saida)->not->toContain($token);
});

it('substitui chave sk', function () use ($redactor) {
    $chave = SecretFixtures::openaiKey();
    $saida = $redactor->redact("Chave {$chave} no cliente.");

    expect($saida)->toContain('[REDACTED:openai_key]')
        ->and($saida)->not->toContain($chave);
});

it('substitui google api key', function () use ($redactor) {
    $chave = SecretFixtures::googleApiKey();
    $saida = $redactor->redact("Gemini {$chave}.");

    expect($saida)->toContain('[REDACTED:google_api_key]')
        ->and($saida)->not->toContain($chave);
});

it('substitui slack token', function () use ($redactor) {
    $token = SecretFixtures::slackBot();
    $saida = $redactor->redact("Slack {$token}.");

    expect($saida)->toContain('[REDACTED:slack_token]')
        ->and($saida)->not->toContain($token);
});

it('substitui jwt', function () use ($redactor) {
    $jwt = SecretFixtures::jwt();
    $saida = $redactor->redact("Bearer jwt {$jwt}");

    expect($saida)->toContain('[REDACTED:jwt]')
        ->and($saida)->not->toContain(SecretFixtures::jwtHeader());
});

it('substitui bloco private key', function () use ($redactor) {
    $pem = SecretFixtures::rsaPrivateKey();
    $saida = $redactor->redact("Chave:\n{$pem}");

    expect($saida)->toContain('[REDACTED:private_key]')
        ->and($saida)->not->toContain('BEGIN RSA PRIVATE KEY')
        ->and($saida)->not->toContain('MIIEowIBAAKCAQEA0Z3CC0w=');
});

it('substitui authorization bearer e par password', function () use ($redactor) {
    $saida = $redactor->redact("Authorization: Bearer super-secreto-token\npassword=SuperSegredo123");

    expect($saida)->toContain('[REDACTED:bearer]')
        ->and($saida)->toContain('[REDACTED:password_pair]')
        ->and($saida)->not->toContain('super-secreto-token')
        ->and($saida)->not->toContain('SuperSegredo123');
});

it('substitui pares secret token e api_key', function () use ($redactor) {
    $saida = $redactor->redact("secret=abc123 token=xyz789 api_key=chave-nossa");

    expect($saida)->toContain('[REDACTED:password_pair]')
        ->and($saida)->not->toContain('abc123')
        ->and($saida)->not->toContain('xyz789')
        ->and($saida)->not->toContain('chave-nossa');
});

it('substitui string de conexao com senha', function () use ($redactor) {
    $saida = $redactor->redact('mysql://gueass:s3nh4@127.0.0.1:3308/gueass_db');

    expect($saida)->toContain('[REDACTED:connection_string]')
        ->and($saida)->not->toContain('s3nh4');
});

it('substitui cpf com digitos validos e ignora invalido', function () use ($redactor) {
    $saida = $redactor->redact('Paciente 529.982.247-25 e falso 111.111.111-11 no cadastro Laravel.');

    expect($saida)->toContain('[REDACTED:cpf]')
        ->and($saida)->not->toContain('529.982.247-25')
        ->and($saida)->toContain('111.111.111-11');
});

it('substitui cnpj com digitos validos e ignora invalido', function () use ($redactor) {
    $saida = $redactor->redact('Hospital 04.252.011/0001-10 e falso 11.111.111/1111-11 no ERP Laravel.');

    expect($saida)->toContain('[REDACTED:cnpj]')
        ->and($saida)->not->toContain('04.252.011/0001-10')
        ->and($saida)->toContain('11.111.111/1111-11');
});

it('substitui cartao com luhn e ignora numero invalido', function () use ($redactor) {
    $saida = $redactor->redact('Pagar com 4111111111111111 e nao com 4111111111111112 no checkout Laravel.');

    expect($saida)->toContain('[REDACTED:card]')
        ->and($saida)->not->toContain('4111111111111111')
        ->and($saida)->toContain('4111111111111112');
});

it('substitui e-mail', function () use ($redactor) {
    $saida = $redactor->redact('Avisar  ana.silva@hospital.example  no job Laravel.');

    expect($saida)->toContain('[REDACTED:email]')
        ->and($saida)->not->toContain('ana.silva@hospital.example');
});

it('nao altera pedido sem segredo', function () use ($redactor) {
    $pedido = 'Criar uma API REST em Laravel com autenticação Sanctum.';

    expect($redactor->redact($pedido))->toBe($pedido);
});

it('redige mapa de variaveis', function () use ($redactor) {
    $chave = SecretFixtures::awsAccessKey();
    $mapa = $redactor->redactMap([
        'NOME' => 'Cliente',
        'CHAVE' => $chave,
    ]);

    expect($mapa['NOME'])->toBe('Cliente')
        ->and($mapa['CHAVE'])->toBe('[REDACTED:aws_access_key]');
});

it('inspect lista os tipos mascarados sem o valor', function () use ($redactor) {
    $chave = SecretFixtures::awsAccessKey();
    $relatorio = $redactor->inspect("Chave {$chave} e e-mail  ana@ex.com ");

    expect($relatorio['types'])->toContain('aws_access_key')
        ->and($relatorio['types'])->toContain('email')
        ->and($relatorio['text'])->not->toContain($chave)
        ->and($relatorio['text'])->not->toContain('ana@ex.com');
});
