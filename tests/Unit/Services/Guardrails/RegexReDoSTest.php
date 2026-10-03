<?php

use App\Services\Guardrails\PromptInjectionPatterns;
use App\Services\Guardrails\SensitiveDataRedactor;
use App\Support\TextNormalizer;

function temposDasRegex(string $payload): array
{
    $padroes = [];

    foreach (PromptInjectionPatterns::categorized() as $categoria => $lista) {
        foreach ($lista as $i => $padrao) {
            $padroes["injection:{$categoria}:{$i}"] = $padrao;
        }
    }

    $refletor = new ReflectionClass(SensitiveDataRedactor::class);
    $constantes = $refletor->getConstant('PATTERNS');
    foreach ($constantes as $tipo => $padrao) {
        $padroes['redactor:'.$tipo] = $padrao;
    }

    $padroes['normalizer:invisiveis'] = '/[\x{00AD}\x{180E}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{206F}\x{FEFF}\x{E0000}-\x{E007F}]/u';
    $padroes['normalizer:espacos'] = '/\s+/u';
    $padroes['sanity:or'] = '/or\s+1\s*=\s*1/i';
    $padroes['sanity:union'] = '/\bunion\s+(all\s+)?select\b/i';

    $tempos = [];
    foreach ($padroes as $nome => $padrao) {
        $inicio = hrtime(true);
        $resultado = @preg_match($padrao, $payload);
        $ms = (hrtime(true) - $inicio) / 1_000_000;
        $tempos[$nome] = [
            'ms' => $ms,
            'pcre_ok' => $resultado !== false,
        ];
    }

    return $tempos;
}

it('regex de guardrail redator e normalizer ficam abaixo de 200ms em 1000 caracteres', function () {
    $payloads = [
        str_repeat('a', 1000),
        str_repeat('ignore ', 140),
        str_repeat('1=1 OR ', 140),
        str_repeat('<script>', 120),
        str_repeat('-----BEGIN ', 80).str_repeat('A', 400),
    ];

    $lento = [];
    foreach ($payloads as $payload) {
        $payload = mb_substr($payload, 0, 1000);
        foreach (temposDasRegex($payload) as $nome => $info) {
            expect($info['pcre_ok'])->toBeTrue();
            if ($info['ms'] > 200) {
                $lento[$nome] = $info['ms'];
            }
        }
    }

    expect($lento)->toBeEmpty();
});

it('normalizer e detector nao estouram em texto patologico', function () {
    $texto = str_repeat('I g n o r e ', 80);
    $inicio = hrtime(true);
    TextNormalizer::forInjection($texto);
    $ms = (hrtime(true) - $inicio) / 1_000_000;

    expect($ms)->toBeLessThan(200);
});
