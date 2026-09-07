<?php

namespace App\Providers;

use App\Contracts\AIProviderInterface;
use App\Services\AI\NullAIProvider;
use Illuminate\Support\ServiceProvider;

class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AIProviderInterface::class, function ($app) {
            $driver = config('ai.provider', 'null');

            return $app->make(config("ai.providers.{$driver}", NullAIProvider::class));
        });
    }
}
