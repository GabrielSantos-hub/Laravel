<?php

namespace App\Services;

/**
 * Regras de fusão da TAREFA e do formato de saída.
 *
 * O pedido do usuário é a fonte da verdade. Templates e briefings só
 * enquadram; não apagam detalhes nem impõem código quando o pedido pede prosa.
 */
class PromptOutputPolicy
{
    public const CODE_TASK_LEAD = 'Sua tarefa é criar uma solução de código';

    public const PROSE_TASK_LEAD = 'Sua tarefa é criar uma especificação técnica em prosa';

    /**
     * @param  array<string, mixed>  $intent
     */
    public function isProseOnly(string $rawIntent, array $intent = []): bool
    {
        $type = is_string($intent['type'] ?? null) ? mb_strtolower(trim($intent['type'])) : '';

        if ($type === 'documentation') {
            return true;
        }

        $haystack = $this->normalize($rawIntent);

        foreach ($intent['constraints'] ?? [] as $constraint) {
            if (is_string($constraint)) {
                $haystack .= ' '.$this->normalize($constraint);
            }
        }

        foreach ([
            'sem codigo',
            'nao quero codigo',
            'nao gerar codigo',
            'nao escreva codigo',
            'nao escreva nenhum codigo',
            'apenas documentacao',
            'somente documentacao',
            'so documentacao',
            'documentacao apenas',
            'apenas prosa',
            'somente prosa',
            'sem implementacao',
            'nao implemente',
            'sem blocos de codigo',
        ] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    public function mergeTaskBody(string $rawIntent, string $briefing): string
    {
        $raw = trim($rawIntent);
        $briefing = trim($briefing);

        if ($raw === '') {
            return $briefing;
        }

        if ($briefing === '') {
            return $raw;
        }

        if (str_contains($briefing, $raw)) {
            return $briefing;
        }

        return $raw."\n\n".$briefing;
    }

    public function stripCodeInstructions(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $kept = [];

        foreach (explode("\n", $text) as $line) {
            if ($this->isCodeInstruction($line)) {
                continue;
            }

            $kept[] = $line;
        }

        $text = implode("\n", $kept);
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    public function rewriteProseFraming(string $text): string
    {
        return preg_replace(
            '/'.preg_quote(self::CODE_TASK_LEAD, '/').'/ui',
            self::PROSE_TASK_LEAD,
            $text
        ) ?? $text;
    }

    /**
     * Remove o pedido cru do enquadramento e limpa pontuação residual
     * (ex.: ",.") deixada pela interpolação.
     */
    public function stripRepeatedRequest(string $framing, string $rawIntent): string
    {
        $framing = trim($framing);
        $rawIntent = trim($rawIntent);

        if ($framing === '' || $rawIntent === '') {
            return $this->cleanFramingPunctuation($framing);
        }

        $framing = preg_replace('/'.preg_quote($rawIntent, '/').'/u', '', $framing) ?? $framing;

        return $this->cleanFramingPunctuation($framing);
    }

    public function cleanFramingPunctuation(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/\s*,\s*\./u', '.', $text) ?? $text;
        $text = preg_replace('/\.\s*,/u', '.', $text) ?? $text;
        $text = preg_replace('/:\s*,/u', ':', $text) ?? $text;
        $text = preg_replace('/,\s*:/u', ':', $text) ?? $text;
        $text = preg_replace('/\.{2,}/u', '.', $text) ?? $text;
        $text = preg_replace('/\s+([.,:;])/u', '$1', $text) ?? $text;
        $text = preg_replace('/[ \t]{2,}/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        $linhas = array_map(
            static fn (string $linha): string => rtrim($linha),
            explode("\n", $text)
        );

        return trim(implode("\n", $linhas));
    }

    /**
     * Se o envelope veio com texto cru antes de PAPEL E CONTEXTO, devolve o
     * prefixo (para ir à TAREFA) e o envelope a partir do papel.
     *
     * @return array{0: string, 1: string} [prefixo, envelope]
     */
    public function splitLeadingPrefix(string $prompt, string $roleHeader): array
    {
        $prompt = ltrim(str_replace(["\r\n", "\r"], "\n", $prompt));
        $pos = strpos($prompt, $roleHeader);

        if ($pos === false) {
            return ['', $prompt];
        }

        if ($pos === 0) {
            return ['', $prompt];
        }

        return [trim(substr($prompt, 0, $pos)), ltrim(substr($prompt, $pos))];
    }

    private function isCodeInstruction(string $line): bool
    {
        $normalized = $this->normalize($line);

        if ($normalized === '') {
            return false;
        }

        foreach ([
            'escreva o codigo',
            'escreva um codigo',
            'escreva codigo',
            'codigo completo',
            'blocos de codigo',
            'bloco de codigo',
            'forneca o codigo',
            'exiba o codigo',
            'producao de um componente de codigo',
            'va direto a estrutura de arquivos e ao codigo',
            'antes de exibir o codigo',
            'antes de escrever qualquer linha de codigo',
            'implementacao completa, pronta para producao',
        ] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);

        return $text;
    }
}
