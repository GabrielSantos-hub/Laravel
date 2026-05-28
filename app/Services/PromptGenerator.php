<?php

namespace App\Services;

use App\Models\Template;
use App\Models\Language;
use App\Models\Architecture;
use App\Models\Framework;

class PromptGenerator
{
    public function render(Template $template, string $userInput, Language $language, Architecture $architecture, ?Framework $framework = null): string
    {
        $corpo = $template->corpo_template;

        // 1. Substitui as variáveis obrigatórias com segurança
        $corpo = str_replace('{language}', $language->nome ?? '', $corpo);
        $corpo = str_replace('{architecture}', $architecture->nome ?? '', $corpo);
        $corpo = str_replace('{user_input}', $userInput, $corpo);

        // 2. Trata o framework condicional se as tags {% if %} existirem no template
        if (str_contains($corpo, '{% if framework %}')) {
            if ($framework && $framework->id !== 'none' && !empty($framework->nome)) {
                $corpo = str_replace(['{% if framework %}', '{% endif %}'], '', $corpo);
                $corpo = str_replace('{framework}', $framework->nome, $corpo);
            } else {
                $corpo = preg_replace('/{% if framework %}.*?{% endif %}/s', '', $corpo);
            }
        } else {
            // Se for o template antigo (sem as tags de controle)
            if ($framework && $framework->id !== 'none' && !empty($framework->nome)) {
                $corpo = str_replace('{framework}', $framework->nome, $corpo);
            } else {
                // Se não foi selecionado framework, removemos a menção a ele para não ficar um espaço em branco
                $corpo = str_replace(['e no framework {framework}', 'no framework {framework}', '{framework}'], '', $corpo);
            }
        }

        // Remove apenas espaços duplos horizontais, PRESERVANDO as quebras de linha (\n)
        $corpo = preg_replace('/[ \t]+/', ' ', $corpo);

        return trim($corpo);
    }
}
