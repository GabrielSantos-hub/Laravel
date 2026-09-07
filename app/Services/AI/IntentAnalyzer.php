<?php

namespace App\Services\AI;

use App\Contracts\AIProviderInterface;
use App\Exceptions\AIProviderException;
use App\Exceptions\InvalidIntentException;
use Throwable;

/**
 * Primeira etapa do pipeline de geração: transforma o texto livre digitado pelo
 * usuário em uma estrutura previsível que as etapas seguintes possam consumir.
 *
 * O contrato de saída é sempre o mesmo, independentemente do que o provedor
 * devolver:
 *
 *   [
 *     'objective'    => string,
 *     'technologies' => array<int, string>,
 *     'architecture' => ?string,
 *     'constraints'  => array<int, string>,
 *     'type'         => string,
 *   ]
 */
class IntentAnalyzer
{
    public const MIN_INPUT_LENGTH = 10;

    /** Alinhado ao limite de `input_text` em GeneratePromptRequest. */
    public const MAX_INPUT_LENGTH = 20000;

    public const MAX_OBJECTIVE_LENGTH = 300;

    /** Tipos aceitos pelo pipeline; qualquer outro valor vira `general`. */
    public const TYPES = ['feature', 'bugfix', 'refactor', 'test', 'documentation', 'analysis', 'architecture', 'generic', 'general'];

    public const DEFAULT_TYPE = 'general';

    public function __construct(
        private readonly AIProviderInterface $provider
    ) {}

    /**
     * @return array{
     *     objective: string,
     *     technologies: array<int, string>,
     *     architecture: string|null,
     *     constraints: array<int, string>,
     *     type: string
     * }
     *
     * @throws InvalidIntentException Quando a entrada é vazia ou curta demais.
     * @throws AIProviderException Quando o provedor de IA falha.
     */
    public function analyze(string $inputText): array
    {
        $sanitized = $this->sanitize($inputText);

        if ($sanitized === '') {
            throw InvalidIntentException::empty();
        }

        $length = mb_strlen($sanitized);

        if ($length < self::MIN_INPUT_LENGTH) {
            throw InvalidIntentException::tooShort($length, self::MIN_INPUT_LENGTH);
        }

        try {
            $payload = $this->provider->analyzeIntent($sanitized);
        } catch (Throwable $e) {
            throw AIProviderException::failed($this->provider->name(), $e);
        }

        return $this->normalize($payload, $sanitized);
    }

    /**
     * Remove marcação, caracteres de controle e ruído de espaçamento, mantendo
     * as quebras de linha que dão sentido à descrição do usuário.
     */
    public function sanitize(string $input): string
    {
        $text = strip_tags($input);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? '';
        $text = preg_replace('/ *\n */u', "\n", $text) ?? '';
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? '';
        $text = trim($text);

        return mb_substr($text, 0, self::MAX_INPUT_LENGTH);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     objective: string,
     *     technologies: array<int, string>,
     *     architecture: string|null,
     *     constraints: array<int, string>,
     *     type: string
     * }
     */
    private function normalize(array $payload, string $sanitizedInput): array
    {
        return [
            'objective' => $this->toObjective($payload['objective'] ?? null, $sanitizedInput),
            'technologies' => $this->toList($payload['technologies'] ?? null),
            'architecture' => $this->toNullableString($payload['architecture'] ?? null),
            'constraints' => $this->toList($payload['constraints'] ?? null),
            'type' => $this->toType($payload['type'] ?? null),
        ];
    }

    private function toObjective(mixed $value, string $sanitizedInput): string
    {
        $objective = $this->toNullableString($value) ?? $this->firstSentence($sanitizedInput);

        return mb_substr($objective, 0, self::MAX_OBJECTIVE_LENGTH);
    }

    /**
     * Aceita array ou string separada por vírgula/ponto e vírgula/quebra de
     * linha, descartando itens vazios e duplicados.
     *
     * @return array<int, string>
     */
    private function toList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[,;\n]+/u', $value) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            $item = $this->toNullableString($item);

            if ($item === null) {
                continue;
            }

            $items[mb_strtolower($item)] ??= $item;
        }

        return array_values($items);
    }

    private function toNullableString(mixed $value): ?string
    {
        if (is_bool($value) || ! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function toType(mixed $value): string
    {
        $type = mb_strtolower($this->toNullableString($value) ?? '');

        return in_array($type, self::TYPES, true) ? $type : self::DEFAULT_TYPE;
    }

    private function firstSentence(string $text): string
    {
        $parts = preg_split('/(?<=[.!?])\s+|\R+/u', $text, 2) ?: [];
        $first = trim($parts[0] ?? '');

        return $first === '' ? $text : $first;
    }
}
