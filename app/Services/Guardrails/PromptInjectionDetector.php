<?php

namespace App\Services\Guardrails;

use App\Support\TextNormalizer;

/**
 * Fonte única de detecção de prompt injection.
 *
 * O InputSanityGuardrail chama esta classe sempre, independente do
 * AI_PROVIDER. O NullAIProvider reutiliza o mesmo veredito.
 */
class PromptInjectionDetector
{
    public function isInjection(string $input): bool
    {
        return $this->detect($input) !== null;
    }

    /**
     * Categoria casada ou null se a entrada não é injection.
     */
    public function detect(string $input): ?string
    {
        if (TextNormalizer::hasAbnormalInvisibles($input)) {
            return PromptInjectionPatterns::CATEGORY_EVASIVE_ENCODING;
        }

        $normalized = TextNormalizer::forInjection($input);
        $folded = TextNormalizer::fold($input);

        foreach (PromptInjectionPatterns::categorized() as $category => $patterns) {
            $haystacks = $this->haystacksFor($category, $normalized, $folded);

            foreach ($patterns as $pattern) {
                foreach ($haystacks as $haystack) {
                    if (preg_match($pattern, $haystack) === 1) {
                        return $category;
                    }
                }
            }
        }

        return $this->compactedCategory($normalized, $folded);
    }

    /**
     * @return list<string>
     */
    private function haystacksFor(string $category, string $normalized, string $folded): array
    {
        return match ($category) {
            PromptInjectionPatterns::CATEGORY_DELIMITER_FORGING,
            PromptInjectionPatterns::CATEGORY_EXFILTRATION,
            PromptInjectionPatterns::CATEGORY_EVASIVE_ENCODING => [$folded, $normalized],
            default => [$normalized, $folded],
        };
    }

    private function compactedCategory(string $normalized, string $folded): ?string
    {
        $compacted = str_replace(' ', '', $normalized.$folded);

        $map = [
            PromptInjectionPatterns::CATEGORY_INSTRUCTION_OVERRIDE => [
                'ignoreprevious', 'ignoreallprevious', 'esquecatodas',
                'disregardprevious', 'disregardall',
            ],
            PromptInjectionPatterns::CATEGORY_ROLE_SWITCH => [
                'youarenowdan', 'dansemrestric', 'doanythingnow',
            ],
            PromptInjectionPatterns::CATEGORY_VERDICT_MANIPULATION => [
                'retornevalidotrue', 'aproveestaentrada',
            ],
        ];

        foreach ($map as $category => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($compacted, $needle)) {
                    return $category;
                }
            }
        }

        return null;
    }
}
