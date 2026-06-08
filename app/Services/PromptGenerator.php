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
        
        $corpo = str_replace('{language}', $language->nome ?? '', $corpo);
        $corpo = str_replace('{architecture}', $architecture->nome ?? '', $corpo);
        $corpo = str_replace('{user_input}', $userInput, $corpo);

        if (str_contains($corpo, '{% if framework %}')) {
            if ($framework && $framework->id !== 'none' && !empty($framework->nome)) {
                $corpo = str_replace(['{% if framework %}', '{% endif %}'], '', $corpo);
                $corpo = str_replace('{framework}', $framework->nome, $corpo);
            } else {
                $corpo = preg_replace('/{% if framework %}.*?{% endif %}/s', '', $corpo);
            }
        } else {

            if ($framework && $framework->id !== 'none' && !empty($framework->nome)) {
                $corpo = str_replace('{framework}', $framework->nome, $corpo);
            } else {

                $corpo = str_replace(['e no framework {framework}', 'no framework {framework}', '{framework}'], '', $corpo);
            }
        }

        $corpo = preg_replace('/[ \t]+/', ' ', $corpo);

        return trim($corpo);
    }
}
