<?php

namespace App\Providers;

use App\Models\Prompt;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        Gate::define('admin', fn (User $user): bool => $user->isAdmin());

        View::composer('layout', function ($view) {
       
            $prompts = Auth::check()
                ? Prompt::query()->where('user_id', Auth::id())->latest()->limit(30)->get()
                : collect();

            $view->with('recentPrompts', $prompts);
        });
    }
}