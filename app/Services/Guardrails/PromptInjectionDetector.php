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
                    $hit = preg_match($pattern, $haystack);
                    if ($hit === false) {
                        return PromptInjectionPatterns::CATEGORY_EVASIVE_ENCODING;
                    }
                    if ($hit === 1) {
                        return $category;
                    }
                }
            }
        }

        return $this->structuralInstructionOverride($normalized, $folded)
            ?? $this->compoundSignals($normalized, $folded)
            ?? $this->compactedCategory($normalized, $folded);
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
                'disregardprevious', 'disregardall', 'discardthesystem',
                'dropallsafety', 'dropallconstraints', 'paredeseguir',
                'suasunicasinstruc', 'semasrestricoesoriginais',
                'rewritelyourinstruct', 'rewriteyourinstruct',
                'disablethedeveloperbriefing',
                'cancelthedeveloperbriefing', 'unboundmodel',
                'unrestrictedcompletion', 'zeroguardrails',
                'desconsideretudooquete', 'esquecooquete',
                'ignoratudoquevieram', 'deixadeladooquevoce',
                'nadadoquetepassaram', 'forgetwhattheytold',
            ],
            PromptInjectionPatterns::CATEGORY_ROLE_SWITCH => [
                'youarenowdan', 'dansemrestric', 'doanythingnow',
                'unfilteredassistant', 'nosafetyfilter', 'nocontentpolicy',
            ],
            PromptInjectionPatterns::CATEGORY_VERDICT_MANIPULATION => [
                'retornevalidotrue', 'aproveestaentrada',
                'alwaysapprovetheuser', 'alwaysapprove',
                'intencaocomoaprovadasempre',
            ],
            PromptInjectionPatterns::CATEGORY_SYSTEM_PROMPT_REVEAL => [
                'hiddenpreamble', 'blocodesistemaoculto',
                'printeverythingabove', 'concealeddeveloperchannel',
                'concealeddeveloperbriefing',
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

    /**
     * Instrução dirigida ao assistente para abandonar o contexto anterior,
     * mesmo sem as palavras «regras» / «system prompt».
     *
     * Não casa prosa sobre o domínio (ignorar alerta, anular job, deixar
     * de lado um checklist) — exige objeto de «o que te disseram» /
     * contexto anterior ou marcador temporal + verbo de descarte.
     */
    private function structuralInstructionOverride(string $normalized, string $folded): ?string
    {
        $text = $normalized.' '.$folded;

        $verbs = '(?:desconsidere|desconsidera|desconsiderar|esqueca|esquece|esquecer|ignore|ignora|ignorar|descarte|descarta|descartar|disregard|forget|abandone|abandona|abandonar|anule|anula|anular)';
        $setAside = '(?:(?:deixe|deixa|deixar)\s+de\s+lado|set\s+aside)';
        $prevTalk = '(?:tudo\s+(?:o\s+)?que\s+te\s+(?:disseram|falaram|passaram|contaram|ensinaram|deram)|o\s+que\s+te\s+(?:disseram|falaram|passaram|contaram|ensinaram|deram)|tudo\s+que\s+(?:vieram\s+te\s+dizer|te\s+vieram\s+dizer)|what\s+they\s+(?:told|said|passed|gave)\s+you|whatever\s+they\s+said|what\s+you\s+were\s+(?:told|given|taught)|everything\s+you\s+were\s+given|all\s+they\s+passed\s+you)';
        $prevContext = '(?:contexto\s+anterior|instruc(?:oes)?\s+anteriores|o\s+que\s+voce\s+sabe|previous\s+context|prior\s+context|o\s+que\s+veio\s+antes)';
        $reset = '(?:antes\s+disso|antes\s+de\s+tudo|daqui\s+pr[ae]\s+frente|daqui\s+para\s+frente|a\s+partir\s+de\s+agora|from\s+now\s+on|from\s+here\s+on|going\s+forward)';

        if (preg_match('/(?:'.$verbs.'|'.$setAside.')\b.{0,80}\b(?:'.$prevTalk.'|'.$prevContext.')/u', $text) === 1) {
            return PromptInjectionPatterns::CATEGORY_INSTRUCTION_OVERRIDE;
        }

        if (preg_match('/(?:'.$prevTalk.')\b.{0,40}\b(?:vale|valem|conta|contam|serve|servem|counts?)\b/u', $text) === 1) {
            return PromptInjectionPatterns::CATEGORY_INSTRUCTION_OVERRIDE;
        }

        if (preg_match('/(?:'.$reset.')\b.{0,80}\b(?:nada\s+do\s+que|none\s+of\s+what|(?:'.$verbs.'|'.$setAside.')\b.{0,40}\b(?:tudo|all|everything|contexto|previous|prior|o\s+que|what\s+they|what\s+you))/u', $text) === 1) {
            return PromptInjectionPatterns::CATEGORY_INSTRUCTION_OVERRIDE;
        }

        return null;
    }

    /**
     * Ataques partidos em duas frases (esquecer o passado + seguir só isto)
     * que isolados não casam um único regex.
     */
    private function compoundSignals(string $normalized, string $folded): ?string
    {
        $text = $normalized.' '.$folded;

        $esquecePassado = preg_match(
            '/\b(forget|esqueca|ignore|descarte|discard)\b.{0,80}\b(told|said|given|disseram|comeco|inicio|start|beginning|antes|anterior)\b/u',
            $text
        ) === 1;
        $sigaSomente = preg_match(
            '/\b(follow|siga|obede|prossiga)\b.{0,40}\b(only|apenas|somente)\b/u',
            $text
        ) === 1;

        if ($esquecePassado && $sigaSomente) {
            return PromptInjectionPatterns::CATEGORY_INSTRUCTION_OVERRIDE;
        }

        if (preg_match('/\bnao obede.{0,60}\b(antes|sistema|dono|instruc|regras)\b/u', $text) === 1) {
            return PromptInjectionPatterns::CATEGORY_INSTRUCTION_OVERRIDE;
        }

        $abandonaBriefing = preg_match(
            '/\b(abandon|solte|revog).{0,40}\b(briefing|constituicao)\b/u',
            $text
        ) === 1;
        $obedeçaSoIsto = preg_match(
            '/\b(obede|execute|siga).{0,40}\b(exclusivamente|somente|so o que|apenas)\b/u',
            $text
        ) === 1;

        if ($abandonaBriefing && $obedeçaSoIsto) {
            return PromptInjectionPatterns::CATEGORY_INSTRUCTION_OVERRIDE;
        }

        return null;
    }
}
