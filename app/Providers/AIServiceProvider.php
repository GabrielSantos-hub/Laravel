<?php

namespace App\Providers;

use App\Contracts\AIProviderInterface;
use App\Services\AI\NullAIProvider;
use App\Services\AI\Providers\GeminiAIProvider;
use Illuminate\Support\ServiceProvider;

class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AIProviderInterface::class, function ($app) {
            $driver = config('ai.provider', 'null');

            return $app->make(config("ai.providers.{$driver}", NullAIProvider::class));
        });

        // O Gemini precisa de binding explícito: o container não sabe resolver
        // os parâmetros escalares do construtor (chave, modelo, timeout).
        $this->app->bind(GeminiAIProvider::class, fn (): GeminiAIProvider => new GeminiAIProvider(
            apiKey: config('services.gemini.key'),
            model: (string) config('services.gemini.model', GeminiAIProvider::DEFAULT_MODEL),
            baseUrl: (string) config('services.gemini.base_url', GeminiAIProvider::DEFAULT_BASE_URL),
            timeout: (int) config('services.gemini.timeout', 15),
            tries: (int) config('services.gemini.tries', 2),
        ));
    }
}
