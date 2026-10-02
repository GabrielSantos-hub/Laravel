<?php

namespace App\Support;

/**
 * Normalização defensiva para detecção de ataques.
 *
 * NFKC, invisíveis, homóglifos e leetspeak existem para o detector de
 * prompt injection; o guardrail de sanidade continua com a normalização
 * própria, que não pode transformar "erro 500" em "erro soo".
 */
class TextNormalizer
{
    public const INVISIBLE_RATIO_LIMIT = 0.15;

    public const INVISIBLE_COUNT_LIMIT = 6;

    /** @var array<string, string> */
    private const HOMOGLYPHS = [
        'а' => 'a', 'е' => 'e', 'о' => 'o', 'р' => 'p', 'с' => 'c',
        'у' => 'y', 'х' => 'x', 'і' => 'i', 'ѕ' => 's', 'ј' => 'j',
        'ԁ' => 'd', 'ɡ' => 'g', 'һ' => 'h',
        'α' => 'a', 'ε' => 'e', 'ο' => 'o', 'ρ' => 'p', 'τ' => 't',
        'ι' => 'i', 'η' => 'n', 'υ' => 'y', 'κ' => 'k', 'χ' => 'x',
        'β' => 'b', 'μ' => 'm', 'ν' => 'v',
        'А' => 'a', 'Е' => 'e', 'О' => 'o', 'Р' => 'p', 'С' => 'c',
        'У' => 'y', 'Х' => 'x',
    ];

    /** @var array<string, string> */
    private const LEET = [
        '@' => 'a', '4' => 'a', '3' => 'e', '1' => 'i', '!' => 'i',
        '0' => 'o', '5' => 's', '7' => 't', '$' => 's',
    ];

    public static function nfkc(string $text): string
    {
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_KC);

            if (is_string($normalized) && $normalized !== '') {
                return $normalized;
            }
        }

        return $text;
    }

    public static function stripInvisibles(string $text): string
    {
        return preg_replace(self::invisiblePattern(), '', $text) ?? $text;
    }

    public static function invisibleCount(string $text): int
    {
        return preg_match_all(self::invisiblePattern(), $text) ?: 0;
    }

    public static function hasAbnormalInvisibles(string $text): bool
    {
        $count = self::invisibleCount($text);

        if ($count >= self::INVISIBLE_COUNT_LIMIT) {
            return true;
        }

        $length = max(mb_strlen($text), 1);

        return $count > 0 && ($count / $length) > self::INVISIBLE_RATIO_LIMIT;
    }

    /**
     * Base compartilhada: NFKC, sem invisíveis, minúsculas, sem acento,
     * homóglifos, espaços colapsados.
     */
    public static function fold(string $text): string
    {
        $text = self::nfkc($text);
        $text = self::stripInvisibles($text);
        $text = mb_strtolower(trim($text));
        $text = strtr($text, self::HOMOGLYPHS);
        $text = strtr($text, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * Inclui leetspeak simples. Só use na detecção de injection.
     */
    public static function forInjection(string $text): string
    {
        $text = self::fold($text);
        $text = strtr($text, self::LEET);
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = self::compactSpacedLetters(trim($text));

        return trim($text);
    }

    /**
     * "I g n o r e" vira "ignore" sem colar o restante da frase.
     */
    public static function compactSpacedLetters(string $text): string
    {
        return preg_replace_callback(
            '/(?<!\p{L})(?:\p{L}\s+){2,}\p{L}(?!\p{L})/u',
            static fn (array $match): string => preg_replace('/\s+/u', '', $match[0]) ?? $match[0],
            $text
        ) ?? $text;
    }

    private static function invisiblePattern(): string
    {
        return '/[\x{00AD}\x{180E}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{206F}\x{FEFF}\x{E0000}-\x{E007F}]/u';
    }
}
