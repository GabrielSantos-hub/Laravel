<?php

namespace App\Services\Guardrails;

/**
 * Padrões de prompt injection em PT e EN, agrupados por categoria.
 *
 * As expressões rodam sobre o texto já normalizado (minúsculas, sem
 * acento, leetspeak desfeito). Pedidos legítimos *sobre* segurança
 * ("criar endpoint com proteção contra SQL injection") não casam aqui.
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
                '/\b(ignore|esqueca|desconsidere|disregard|forget|sobrescreva|descarte)\b.{0,50}\b(instruc|regras|rules|prompt|system)\b/u',
                '/\b(ignore|esqueca|disregard|forget)\b.{0,30}\b(anteriores|previous|all previous|todas as)\b/u',
                '/\boverride\b.{0,30}\b(system|previous|instruc|rules|regras)\b/u',
                '/\bignore (the )?(above|previous)\b/u',
            ],
            self::CATEGORY_SYSTEM_PROMPT_REVEAL => [
                '/\b(mostre|revele|repita|exiba|print|show|reveal|repeat|dump)\b.{0,50}\b(system prompt|system instruction|prompt de sistema|suas instruc|your instruct|instrucoes)\b/u',
                '/\b(system prompt|prompt de sistema|system instruction)\b.{0,30}\b(completo|verbatim|inteiro|secret)\b/u',
                '/\binstrucoes secretas\b/u',
                '/\brepeat your instruct/u',
            ],
            self::CATEGORY_ROLE_SWITCH => [
                '/\b(voce agora e|you are now|atue como|act as|pretenda ser|pretend to be)\b.{0,40}\b(dan|jailbreak|unrestricted|sem restric|god mode|developer mode)\b/u',
                '/\b(modo desenvolvedor|developer mode|god mode|do anything now)\b/u',
                '/\batue como dan\b/u',
                '/\bact as dan\b/u',
            ],
            self::CATEGORY_JAILBREAK => [
                '/\bjailbreak\s*:/u',
                '/\b(enable|activate|start|ative|inicie)\b.{0,20}\bjailbreak\b/u',
                '/\bthis is (a )?jailbreak\b/u',
                '/\bdan sem restric/u',
                '/\[inst\]/u',
            ],
            self::CATEGORY_VERDICT_MANIPULATION => [
                '/\bretorne valido\s*true\b/u',
                '/\baprove esta entrada\b/u',
                '/\bapprove this (entry|input|prompt)?\b/u',
                '/\bset valido\s*true\b/u',
                '/\bvalido\s*[:=]\s*true\b/u',
                '/\bvalido true\b/u',
            ],
            self::CATEGORY_DELIMITER_FORGING => [
                '/<\/system>/u',
                '/###\s*system\b/u',
                '/```\s*system\b/u',
                '/(?:^|[\n])system\s*:/u',
                '/(?:^|[\n])assistant\s*:/u',
                '/<<<+\s*system\b/u',
                '/\[system\]/u',
            ],
            self::CATEGORY_EXFILTRATION => [
                '/\b(envie|send|exfiltra)\b.{0,50}\b(system prompt|prompt de sistema|api key|apikey|chave|credencia|\.env|instrucoes)\b.{0,40}\bhttps?:\/\//u',
                '/\b(mostre|revele|dump|print|exiba|show)\b.{0,40}\b(api key|apikey|gemini_api|variaveis de ambiente|\.env|credencia)\b/u',
                '/\bcat\s+\.env\b/u',
            ],
            self::CATEGORY_EVASIVE_ENCODING => [
                '/\b(decode|decodifique|descodifique)\b.{0,30}\bbase64\b/u',
                '/\bbase64\b.{0,30}\b(decode|decodifique|execute|instruc)\b/u',
                '/\brot13\b/u',
                '/\beval\s*\(\s*base64/u',
            ],
        ];
    }
}
