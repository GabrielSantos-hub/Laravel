<?php

namespace App\Services;

use App\Models\Architecture;
use App\Models\Framework;
use App\Models\Language;
use App\Models\Template;

class PromptGenerator
{
    /**
     * Substitui placeholders no corpo do template. Use no texto:
     * {{intencao}}, {{linguagem}}, {{linguagem_slug}}, {{framework}}, {{arquitetura}}, {{descricao_arquitetura}}
     */
    public function render(
        Template $template,
        string $intencao,
        Language $language,
        Architecture $architecture,
        ?Framework $framework = null,
    ): string {
        $map = [
            '{{intencao}}' => $intencao,
            '{{linguagem}}' => $language->nome,
            '{{linguagem_slug}}' => $language->slug,
            '{{framework}}' => $framework?->nome ?? '',
            '{{arquitetura}}' => $architecture->nome,
            '{{descricao_arquitetura}}' => $architecture->descricao,
        ];

        return strtr($template->corpo_template, $map);
    }
}
