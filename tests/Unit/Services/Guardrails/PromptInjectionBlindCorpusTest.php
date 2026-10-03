<?php

use App\Services\Guardrails\InputSanityGuardrail;
use App\Services\Guardrails\PromptInjectionDetector;
use Tests\Unit\Services\Guardrails\PromptInjectionBlindCorpus;

$detector = new PromptInjectionDetector;
$guardrail = new InputSanityGuardrail($detector);

it('o corpus cego tem trinta ataques e trinta pedidos legitimos', function () {
    expect(PromptInjectionBlindCorpus::ataques())->toHaveCount(30)
        ->and(PromptInjectionBlindCorpus::legitimos())->toHaveCount(30);
});

it('mede taxas do corpus cego e grava o relatorio', function () use ($detector, $guardrail) {
    $ataques = PromptInjectionBlindCorpus::ataques();
    $legitimos = PromptInjectionBlindCorpus::legitimos();

    $ataquesQuePassaram = [];
    $ataquesBloqueados = 0;
    foreach ($ataques as $rotulo => [$texto]) {
        if ($detector->isInjection($texto) || $guardrail->isMalicious($texto)) {
            $ataquesBloqueados++;
        } else {
            $ataquesQuePassaram[$rotulo] = $texto;
        }
    }

    $legitimosBloqueados = [];
    $legitimosAceitos = 0;
    foreach ($legitimos as $rotulo => [$texto]) {
        if (! $detector->isInjection($texto) && $guardrail->assess($texto)->accepted) {
            $legitimosAceitos++;
        } else {
            $legitimosBloqueados[$rotulo] = $texto;
        }
    }

    $relatorio = [
        'ataques_total' => count($ataques),
        'ataques_bloqueados' => $ataquesBloqueados,
        'legitimos_total' => count($legitimos),
        'legitimos_aceitos' => $legitimosAceitos,
        'ataques_que_passaram' => $ataquesQuePassaram,
        'legitimos_bloqueados' => $legitimosBloqueados,
    ];

    $destino = sys_get_temp_dir().DIRECTORY_SEPARATOR.'gueass-blind-corpus-rates.json';
    file_put_contents($destino, json_encode($relatorio, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    expect($ataques)->toHaveCount(30)
        ->and($legitimos)->toHaveCount(30)
        ->and($ataquesQuePassaram)->toBe([])
        ->and($legitimosBloqueados)->toBe([]);
});
