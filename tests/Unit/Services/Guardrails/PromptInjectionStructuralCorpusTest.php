<?php

use App\Services\Guardrails\InputSanityGuardrail;
use App\Services\Guardrails\PromptInjectionDetector;
use Tests\Unit\Services\Guardrails\PromptInjectionStructuralCorpus;

$detector = new PromptInjectionDetector;
$guardrail = new InputSanityGuardrail($detector);

it('o corpus estrutural tem quinze ataques e quinze pedidos legitimos', function () {
    expect(PromptInjectionStructuralCorpus::ataques())->toHaveCount(15)
        ->and(PromptInjectionStructuralCorpus::legitimos())->toHaveCount(15);
});

it('mede taxas brutas do corpus estrutural uma vez e grava o relatorio', function () use ($detector, $guardrail) {
    $ataques = PromptInjectionStructuralCorpus::ataques();
    $legitimos = PromptInjectionStructuralCorpus::legitimos();

    $ataquesQuePassaram = [];
    $ataquesBloqueados = [];
    foreach ($ataques as $rotulo => [$texto]) {
        if ($detector->isInjection($texto) || $guardrail->isMalicious($texto)) {
            $ataquesBloqueados[$rotulo] = $texto;
        } else {
            $ataquesQuePassaram[$rotulo] = $texto;
        }
    }

    $legitimosBloqueados = [];
    $legitimosAceitos = [];
    foreach ($legitimos as $rotulo => [$texto]) {
        if (! $detector->isInjection($texto) && $guardrail->assess($texto)->accepted) {
            $legitimosAceitos[$rotulo] = $texto;
        } else {
            $legitimosBloqueados[$rotulo] = $texto;
        }
    }

    $relatorio = [
        'ataques_total' => count($ataques),
        'ataques_bloqueados' => count($ataquesBloqueados),
        'ataques_que_passaram' => $ataquesQuePassaram,
        'ataques_bloqueados_rotulos' => array_keys($ataquesBloqueados),
        'legitimos_total' => count($legitimos),
        'legitimos_aceitos' => count($legitimosAceitos),
        'legitimos_bloqueados' => $legitimosBloqueados,
    ];

    $destino = sys_get_temp_dir().DIRECTORY_SEPARATOR.'gueass-structural-corpus-rates.json';
    file_put_contents($destino, json_encode($relatorio, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    expect($ataques)->toHaveCount(15)
        ->and($legitimos)->toHaveCount(15)
        ->and($ataquesQuePassaram)->toBe([])
        ->and($legitimosBloqueados)->toBe([]);
});
