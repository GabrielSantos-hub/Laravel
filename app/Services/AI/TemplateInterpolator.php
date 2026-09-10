<?php

namespace App\Services\AI;

/**
 * Interpolação determinística do corpo de um template.
 *
 * É a mesma sintaxe que os templates do GUEASS já usam: placeholders `{chave}`
 * e blocos opcionais `{% if chave %}...{% endif %}`, mantidos apenas quando a
 * variável correspondente tem valor.
 *
 * Serve a dois usos: é a composição do NullAIProvider (que trabalha offline) e
 * é o fallback do PromptComposer quando o provedor de IA falha.
 */
class TemplateInterpolator
{
    /**
     * Marcadores que o pipeline preenche sozinho, a partir da intenção
     * estruturada (ver PromptComposer::variables). Tudo o que aparece no corpo
     * fora desta lista é variável dinâmica: quem preenche é o usuário.
     *
     * @var array<int, string>
     */
    public const RESERVED_VARIABLES = [
        'user_input',
        'objective',
        'type',
        'architecture',
        'technologies',
        'language',
        'framework',
        'constraints',
    ];

    /**
     * Resolve os placeholders e normaliza o espaçamento do resultado.
     *
     * @param  array<string, mixed>  $variables
     */
    public function render(string $body, array $variables): string
    {
        return $this->tidy($this->resolvePlaceholders($body, $variables));
    }

    /**
     * Resolve os placeholders preservando o espaçamento original.
     *
     * Usado como rede de segurança sobre o texto devolvido pelo LLM, onde a
     * indentação pode ser significativa (blocos de código, listas aninhadas).
     *
     * @param  array<string, mixed>  $variables
     */
    public function resolvePlaceholders(string $body, array $variables): string
    {
        $variables = $this->normalizeVariables($variables);

        return $this->replaceTokens($this->resolveConditionals($body, $variables), $variables);
    }

    /**
     * Lista os marcadores declarados no corpo do template, tanto os
     * placeholders `{CHAVE}` quanto as condições `{% if CHAVE %}`.
     *
     * O padrão exige um identificador entre as chaves, então trechos de código
     * e JSON de exemplo (`{"nome": "valor"}`, `{}`) ficam de fora. A ordem de
     * aparição é preservada para o formulário sair na mesma sequência do texto.
     *
     * @param  bool  $includeReserved  Inclui o que o pipeline já preenche.
     * @return array<int, string>
     */
    public function extractVariables(string $body, bool $includeReserved = false): array
    {
        preg_match_all(
            '/\{([A-Za-z_][A-Za-z0-9_]*)\}|\{%\s*if\s+([A-Za-z_][A-Za-z0-9_]*)\s*%\}/u',
            $body,
            $matches,
            PREG_SET_ORDER
        );

        $names = [];

        foreach ($matches as $match) {
            $name = $match[1] !== '' ? $match[1] : ($match[2] ?? '');

            if ($name === '' || in_array($name, $names, true)) {
                continue;
            }

            if (! $includeReserved && in_array($name, self::RESERVED_VARIABLES, true)) {
                continue;
            }

            $names[] = $name;
        }

        return $names;
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, string>
     */
    private function normalizeVariables(array $variables): array
    {
        $normalized = [];

        foreach ($variables as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            $normalized[$key] = is_scalar($value) && ! is_bool($value) ? trim((string) $value) : '';
        }

        return $normalized;
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function resolveConditionals(string $body, array $variables): string
    {
        return preg_replace_callback(
            '/\{%\s*if\s+(\w+)\s*%\}(.*?)\{%\s*endif\s*%\}/su',
            fn (array $matches): string => ($variables[$matches[1]] ?? '') !== '' ? $matches[2] : '',
            $body
        ) ?? $body;
    }

    /**
     * Só substitui as chaves conhecidas: chaves soltas no texto (exemplos de
     * código, JSON) permanecem intactas.
     *
     * @param  array<string, string>  $variables
     */
    private function replaceTokens(string $body, array $variables): string
    {
        $search = [];
        $replace = [];

        foreach ($variables as $key => $value) {
            $search[] = '{'.$key.'}';
            $replace[] = $value;
        }

        return str_replace($search, $replace, $body);
    }

    private function tidy(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $body = preg_replace('/[ \t]+/u', ' ', $body) ?? $body;
        $body = preg_replace('/ *\n */u', "\n", $body) ?? $body;
        $body = preg_replace('/\n{3,}/u', "\n\n", $body) ?? $body;

        return trim($body);
    }
}
