<?php

use App\Services\AI\NullAIProvider;
use App\Services\AI\Providers\GeminiAIProvider;

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
    | O driver "gemini" exige GEMINI_API_KEY. Sem ela o provedor falha de
    | propósito e o pipeline degrada para o "null", registrando um warning.
    |
    */

    'providers' => [
        'null' => NullAIProvider::class,
        'gemini' => GeminiAIProvider::class,
    ],

];
