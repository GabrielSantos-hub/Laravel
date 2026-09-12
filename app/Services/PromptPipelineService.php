<?php

namespace App\Services;

/**
 * Fachada estável do pipeline de geração. A orquestração (JSON estruturado
 * da IA + seleção local de template) vive em PromptGeneratorService.
 */
class PromptPipelineService
{
    public function __construct(
        private readonly PromptGeneratorService $generator,
    ) {}

    /**
     * @param  array{language_id?: int|null, framework_id?: int|null, architecture_id?: int|null}  $catalogHints
     * @param  array<string, mixed>  $customVariables
     */
    public function generate(
        string $userInput,
        array $catalogHints = [],
        array $customVariables = [],
    ): PromptPipelineResult {
        return $this->generator->generate($userInput, $catalogHints, $customVariables);
    }
}
