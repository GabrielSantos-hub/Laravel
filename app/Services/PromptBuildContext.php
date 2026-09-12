<?php

namespace App\Services;

/**
 * Entrada normalizada do pipeline de montagem do prompt final.
 *
 * O pedido cru não mora aqui: ele entra só na TAREFA. Este contexto descreve
 * o papel, o ambiente e se a saída deve ser apenas prosa.
 */
readonly class PromptBuildContext
{
    /**
     * @param  list<string>  $technologies
     */
    public function __construct(
        public string $type,
        public array $technologies,
        public ?string $architecture,
        public bool $proseOnly = false,
        public bool $lean = false,
    ) {}
}
