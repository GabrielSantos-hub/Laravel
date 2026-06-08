<?php

namespace App\Providers;

use App\Models\Prompt;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth; 

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layout', function ($view) {
            $prompts = Auth::check()
                ? Prompt::query()->where('user_id', Auth::id())->latest()->limit(30)->get()
                : collect();

            $view->with('recentPrompts', $prompts);
        });
    }
}