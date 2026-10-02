<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Retenção do histórico de prompts
    |--------------------------------------------------------------------------
    |
    | O comando `gueass:prune-prompts` apaga prompts mais antigos que este
    | número de dias. Só a contagem entra no log, nunca o conteúdo.
    |
    */

    'prompt_retention_days' => (int) env('PROMPT_RETENTION_DAYS', 90),

];
