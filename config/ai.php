<?php

use App\Services\AI\NullAIProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Provedor de IA padrão
    |--------------------------------------------------------------------------
    |
    | Define qual implementação de AIProviderInterface o container resolve.
    | O driver "null" é offline e determinístico: não faz nenhuma chamada de
    | rede, o que mantém a suíte de testes independente de API externa.
    |
    */

    'provider' => env('AI_PROVIDER', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Drivers disponíveis
    |--------------------------------------------------------------------------
    |
    | Mapa de driver => classe. Novos provedores (OpenAI, Anthropic, Ollama...)
    | só precisam implementar AIProviderInterface e ser listados aqui.
    |
    */

    'providers' => [
        'null' => NullAIProvider::class,
    ],

];
