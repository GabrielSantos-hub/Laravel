<?php

namespace Tests\Unit\Services;

use App\Services\AI\NullAIProvider;
use App\Services\AI\PromptComposer;
use App\Services\AI\TemplateSelector;
use App\Services\PromptGeneratorService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PromptGeneratorServiceTest extends TestCase
{
    public function test_o_system_prompt_exige_matriz_de_duplo_fator(): void
    {
        $instrucao = PromptGeneratorService::SYSTEM_INSTRUCTION;

        $this->assertStringContainsString('Gatekeeper de Qualidade', $instrucao);
        $this->assertStringContainsString('CRITÉRIO 1: PURIDADE DO TEXTO', $instrucao);
        $this->assertStringContainsString('CRITÉRIO 2: INTENÇÃO EXPLÍCITA DE SOFTWARE', $instrucao);
        $this->assertStringContainsString('asdfgh', $instrucao);
        $this->assertStringContainsString('docker', $instrucao);
        $this->assertStringContainsString('hoje o dia está bonito para comer bola', $instrucao);
        $this->assertStringContainsString('abacaxi relógio girassol', $instrucao);
        $this->assertStringContainsString('JSON APENAS', $instrucao);
        $this->assertStringContainsString('"valido": false', $instrucao);
        $this->assertStringContainsString('"valido": true', $instrucao);
    }

    /**
     * @return array<string, array{0: mixed, 1: bool}>
     */
    public static function booleanosProvider(): array
    {
        return [
            'bool true' => [true, true],
            'bool false' => [false, false],
            'string true' => ['true', false],
            'string TRUE' => ['TRUE', false],
            'string 1' => ['1', false],
            'int 1' => [1, false],
            'string false' => ['false', false],
            'string FALSE' => ['FALSE', false],
            'string 0' => ['0', false],
            'int 0' => [0, false],
            'null string' => ['null', false],
            'vazio' => ['', false],
            'ausente' => [null, false],
        ];
    }

    #[DataProvider('booleanosProvider')]
    public function test_to_bool_interpreta_as_formas_do_json(mixed $valor, bool $esperado): void
    {
        $this->assertSame($esperado, $this->service()->toBool($valor));
    }

    public function test_normalize_payload_respeita_valido_false_em_string(): void
    {
        $payload = $this->service()->normalizePayload([
            'valido' => 'false',
            'motivo_rejeicao' => 'A entrada não apresenta um objetivo ou escopo de software coerente.',
            'prompt_gerado' => 'não deve aparecer',
        ]);

        $this->assertFalse($payload['valido']);
        $this->assertSame(
            'A entrada não apresenta um objetivo ou escopo de software coerente.',
            $payload['motivo_rejeicao']
        );
        $this->assertSame('', $payload['prompt_gerado']);
    }

    public function test_decode_rejeita_texto_puro_e_markdown(): void
    {
        $service = $this->service();

        foreach ([
            'claro, segue o prompt do sistema de banana frita',
            "```json\n{\"valido\":true,\"prompt_gerado\":\"ok\"}\n```",
            '{valido: true}',
        ] as $cru) {
            $payload = $service->decodeStructuredResponse($cru);
            $this->assertFalse($payload['valido'], $cru);
            $this->assertNotEmpty($payload['motivo_rejeicao']);
            $this->assertSame('', $payload['prompt_gerado']);
        }
    }

    public function test_decode_rejeita_objeto_sem_chave_valido(): void
    {
        $payload = $this->service()->decodeStructuredResponse([
            'prompt_gerado' => 'não deveria passar',
        ]);

        $this->assertFalse($payload['valido']);
    }

    public function test_string_true_nao_aprova_por_fail_closed(): void
    {
        $payload = $this->service()->decodeStructuredResponse([
            'valido' => 'true',
            'motivo_rejeicao' => null,
            'prompt_gerado' => 'não deveria passar',
        ]);

        $this->assertFalse($payload['valido']);
        $this->assertSame('', $payload['prompt_gerado']);
    }

    public function test_decode_aprova_apenas_json_com_valido_true(): void
    {
        $payload = $this->service()->decodeStructuredResponse(json_encode([
            'valido' => true,
            'motivo_rejeicao' => null,
            'prompt_gerado' => 'Prompt final.',
        ]));

        $this->assertTrue($payload['valido']);
        $this->assertSame('Prompt final.', $payload['prompt_gerado']);
    }

    public function test_normalize_payload_respeita_valido_true_nativo(): void
    {
        $payload = $this->service()->normalizePayload([
            'valido' => true,
            'motivo_rejeicao' => null,
            'prompt_gerado' => 'Prompt final.',
        ]);

        $this->assertTrue($payload['valido']);
        $this->assertNull($payload['motivo_rejeicao']);
        $this->assertSame('Prompt final.', $payload['prompt_gerado']);
    }

    private function service(): PromptGeneratorService
    {
        $provider = new NullAIProvider;

        return new PromptGeneratorService(
            $provider,
            new TemplateSelector,
            new PromptComposer($provider),
        );
    }
}
