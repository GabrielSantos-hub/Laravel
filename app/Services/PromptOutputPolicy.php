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

        if (in_array($type, ['documentation'], true)) {
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
            'especificacao tecnica',
            'especifique a',
            'especifique um',
            'especifique o',
            'elaborar a especificacao',
            'elabore a especificacao',
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
    /**
     * @param  list<string>|array<int, mixed>  $technologies
     * @return array{0: string, 1: string} [language, framework]
     */
    public function languageAndFramework(array $technologies): array
    {
        $language = '';
        $framework = '';

        foreach ($technologies as $item) {
            if (! is_string($item)) {
                continue;
            }

            $item = trim($item);

            if ($item === '') {
                continue;
            }

            $kind = $this->technologyKind($item);

            if ($kind === 'database') {
                continue;
            }

            if ($kind === 'language' && $language === '') {
                $language = $item;

                continue;
            }

            if ($kind === 'framework' && $framework === '') {
                $framework = $item;
            }
        }

        return [$language, $framework];
    }

    public function stripGuestIntentBlocks(string $text): string
    {
        $stripped = preg_replace('/<<<GUEASS_USER_INTENT>>>.*?<<<END_GUEASS_USER_INTENT>>>/su', '', $text, -1, $count);

        return $this->cleanedIfChanged($text, $stripped, $count);
    }

    public function stripCompetingOutputSchemas(string $text): string
    {
        $stripped = preg_replace(
            '/\n*FORMATO DE SAÍDA:.*?(?=\n(?:RESTRIÇÕES NEGATIVAS|ESQUEMA DE SAÍDA|AUTO-VALIDAÇÃO)|\z)/isu',
            '',
            str_replace(["\r\n", "\r"], "\n", $text),
            -1,
            $count
        );

        return $this->cleanedIfChanged($text, $stripped, $count);
    }

    public function omitEmptyOptionalSections(string $text): string
    {
        $stripped = preg_replace(
            '/^(Erro ou stack trace|Gargalo descrito|Pedido de migração|O foco do negócio é):[ \t]*$/miu',
            '',
            str_replace(["\r\n", "\r"], "\n", $text),
            -1,
            $count
        );

        return $this->cleanedIfChanged($text, $stripped, $count);
    }

    public function stripInflatedPhrases(string $text): string
    {
        $stripped = preg_replace(
            ['/\s*em prosa contínua/iu', '/,?\s*pronta para produção/iu'],
            '',
            $text,
            -1,
            $count
        );

        return $this->cleanedIfChanged($text, $stripped, $count);
    }

    /**
     * Só normaliza pontuação/espaços quando algo foi removido: texto do LLM
     * sem marcas a limpar mantém a indentação original.
     */
    private function cleanedIfChanged(string $original, ?string $stripped, int $count): string
    {
        return $stripped === null || $count === 0 ? $original : $this->cleanFramingPunctuation($stripped);
    }

    public function isMismatchedFraming(string $framing, string $rawIntent): bool
    {
        $intent = $this->normalize($rawIntent);
        $frame = $this->normalize($framing);

        if ($frame === '' || $intent === '') {
            return false;
        }

        foreach ([
            'pipelinedecicd' => ['pipeline', 'cicd', 'devops'],
            'errooustacktrace' => ['stack', 'trace', 'depur', 'exception', 'erro', 'bug', 'corrig'],
        ] as $marker => $needles) {
            if (! str_contains($frame, $marker)) {
                continue;
            }

            foreach ($needles as $needle) {
                if (str_contains($intent, $this->normalize($needle))) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

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

    private function technologyKind(string $name): string
    {
        $normalized = $this->normalize($name);

        foreach (['mysql', 'postgresql', 'postgres', 'sqlite', 'mongodb', 'mongo', 'redis'] as $database) {
            if ($normalized === $database) {
                return 'database';
            }
        }

        foreach ([
            'php', 'javascript', 'js', 'typescript', 'python', 'java', 'c#', 'csharp',
            'go', 'golang', 'kotlin', 'swift', 'rust', 'ruby', 'dart', 'c++', 'c',
            'html', 'css', 'sql',
        ] as $language) {
            if ($normalized === $this->normalize($language)) {
                return 'language';
            }
        }

        return 'framework';
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
