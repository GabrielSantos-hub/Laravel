<?php

namespace App\Services\Guardrails;

/**
 * Padrões de prompt injection em PT e EN, agrupados por categoria.
 *
 * As expressões rodam sobre o texto já normalizado (minúsculas, sem
 * acento, leetspeak desfeito) e, nas categorias de delimitador/exfil/encoding,
 * também sobre o texto só com fold (pontuação preservada). Pedidos
 * legítimos *sobre* segurança não casam aqui.
 */
class PromptInjectionPatterns
{
    public const CATEGORY_INSTRUCTION_OVERRIDE = 'instruction_override';

    public const CATEGORY_SYSTEM_PROMPT_REVEAL = 'system_prompt_reveal';

    public const CATEGORY_ROLE_SWITCH = 'role_switch';

    public const CATEGORY_JAILBREAK = 'jailbreak';

    public const CATEGORY_VERDICT_MANIPULATION = 'verdict_manipulation';

    public const CATEGORY_DELIMITER_FORGING = 'delimiter_forging';

    public const CATEGORY_EXFILTRATION = 'exfiltration';

    public const CATEGORY_EVASIVE_ENCODING = 'evasive_encoding';

    /**
     * @return array<string, list<string>>
     */
    public static function categorized(): array
    {
        return [
            self::CATEGORY_INSTRUCTION_OVERRIDE => [
                '/\b(ignore|esqueca|desconsidere|disregard|forget|sobrescreva|descarte|discard)\b.{0,50}\b(instruc|regras|rules|prompt|system|diretrizes|directives)\b/u',
                '/\b(ignore|esqueca|disregard|forget)\b.{0,30}\b(anteriores|previous|all previous|todas as)\b/u',
                '/\boverride\b.{0,30}\b(system|previous|instruc|rules|regras|hidden)\b/u',
                '/\bignore (the )?(above|previous)\b/u',
                '/\b(drop|solte|descarte)\b.{0,20}\b(all )?(safety |content )?(constraints|restric|regras|guidelines)\b/u',
                '/\b(pare de seguir|stop following|nao obede|nao siga)\b.{0,40}\b(diretrizes|regras|rules|instruc|safety|sistema|developer message)\b/u',
                '/\b(suas unicas instruc|your only (remaining )?instruction)/u',
                '/\b(opere|operate|prossiga|proceed|continue)\b.{0,40}\b(sem as restricoes originais|without (your )?original|unrestricted|sem (as )?politicas)\b/u',
                '/\b(continue|prossiga)\b.{0,30}\bcomo se\b.{0,40}\b(politicas|regras|guidelines|safety)\b.{0,30}\b(nunca|never)\b/u',
                '/\b(trate|treat)\b.{0,60}\b(texto|text|content|input|mensagem).{0,60}(instruc|system|sistema)/u',
                '/\brewrite your instruct/u',
                '/\bnew policy\s*:/u',
                '/\b(previous|prior) (developer )?(directives|instructions|rules).{0,25}\b(void|invalid|nulas?|cancel)/u',
                '/\b(pretend|finja)\b.{0,40}\b(previous|anterior|developer).{0,40}\b(never existed|nunca exist)/u',
                '/\b(supersede|substitua|no lugar d[aeo])\b.{0,30}\b(system prompt|prompt de sistema|instruc)\b/u',
                '/\badmin override\s*:/u',
                '/\bfrom now on\b.{0,40}\b(discard|ignore|forget)\b.{0,30}\b(developer message|system|instruc)\b/u',
                '/\b(antigas? nao valem|as antigas nao valem)\b/u',
            ],
            self::CATEGORY_SYSTEM_PROMPT_REVEAL => [
                '/\b(mostre|revele|repita|exiba|print|show|reveal|repeat|dump|echo|output|transcreva|transcrev|copy)\b.{0,50}\b(system prompt|system instruction|prompt de sistema|suas instruc|your instruct|instrucoes|hidden preamble|preambulo oculto|bloco de sistema|initial configuration|concealed system|hidden prompt|hidden rules|internal directives)\b/u',
                '/\b(system prompt|prompt de sistema|system instruction)\b.{0,30}\b(completo|verbatim|inteiro|secret)\b/u',
                '/\binstrucoes secretas\b/u',
                '/\brepeat your instruct/u',
                '/\b(hidden preamble|preambulo oculto|bloco de sistema oculto|concealed system block)\b/u',
                '/\b(print|mostre|dump)\b.{0,25}\b(everything|tudo)\b.{0,25}\b(above|acima)\b/u',
                '/\bbegin your reply with the hidden\b/u',
                '/\blist every rule you were given\b/u',
                '/\breveal internal directives\b/u',
            ],
            self::CATEGORY_ROLE_SWITCH => [
                '/\b(voce agora e|you are now|atue como|act as|pretenda ser|pretend to be)\b.{0,40}\b(dan|jailbreak|unrestricted|sem restric|god mode|developer mode)\b/u',
                '/\b(modo desenvolvedor|developer mode|god mode|do anything now)\b/u',
                '/\batue como dan\b/u',
                '/\bact as dan\b/u',
                '/\b(unfiltered assistant|assistant persona)\b/u',
                '/\b(persona|personagem).{0,40}\b(no safety|sem filtro|unrestricted|irrestrit)\b/u',
                '/\b(switch to|mude para)\b.{0,30}\bunfiltered\b/u',
                '/\bact without the safety layer\b/u',
                '/\b(no content policy|had no content policy)\b/u',
            ],
            self::CATEGORY_JAILBREAK => [
                '/\bjailbreak\s*:/u',
                '/\b(enable|activate|start|ative|inicie)\b.{0,20}\bjailbreak\b/u',
                '/\bthis is (a )?jailbreak\b/u',
                '/\bdan sem restric/u',
                '/\[inst\]/u',
                '/\bdrop all constraints\b/u',
                '/\bsafety guidelines no longer apply\b/u',
                '/\bsimulation requires you to drop\b/u',
            ],
            self::CATEGORY_VERDICT_MANIPULATION => [
                '/\bretorne valido\s*true\b/u',
                '/\baprove esta entrada\b/u',
                '/\bapprove this (entry|input|prompt)?\b/u',
                '/\bset valido\s*true\b/u',
                '/\bvalido\s*[:=]\s*true\b/u',
                '/\bvalido true\b/u',
                '/\b(force|force o|obrigue)\b.{0,40}\bvalido\b/u',
                '/\bsempre responda valido\b/u',
            ],
            self::CATEGORY_DELIMITER_FORGING => [
                '/<\/system>/u',
                '/###\s*system\b/u',
                '/```\s*system\b/u',
                '/(?:^|[\n])system\s*:/u',
                '/(?:^|[\n])assistant\s*:/u',
                '/<<<+\s*system\b/u',
                '/\[system\]/u',
                '/"role"\s*:\s*"system"/u',
                '/\{[^{}]{0,80}"instruction"\s*:/u',
            ],
            self::CATEGORY_EXFILTRATION => [
                '/\b(envie|send|exfiltra|post)\b.{0,50}\b(system prompt|prompt de sistema|api key|apikey|chave|credencia|\.env|instrucoes|hidden instructions)\b.{0,40}\bhttps?:\/\//u',
                '/\b(mostre|revele|dump|print|exiba|show)\b.{0,40}\b(api key|apikey|gemini_api|variaveis de ambiente|\.env|credencia|getenv|api credentials)\b/u',
                '/\bcat\s+\.env\b/u',
                '/\bdump getenv\b/u',
                '/\bpost the hidden\b.{0,40}\bhttps?:\/\//u',
            ],
            self::CATEGORY_EVASIVE_ENCODING => [
                '/\b(decode|decodifique|descodifique)\b.{0,30}\bbase64\b/u',
                '/\bbase64\b.{0,30}\b(decode|decodifique|execute|instruc|policy|politica)\b/u',
                '/\brot13\b/u',
                '/\beval\s*\(\s*base64/u',
                '/\b(hex|hexadecimal)\b.{0,40}\b(decode|decodif|execute|siga|follow|instruc|rules|regras)\b/u',
                '/\b(decode|decodifique|convert from)\b.{0,40}\b(hex|hexadecimal)\b/u',
                '/\bbase64-decode\b/u',
            ],
        ];
    }
}
