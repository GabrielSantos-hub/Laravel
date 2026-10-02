<?php

namespace App\Providers;

use App\Models\Prompt;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        // Em production o app assume HTTPS (cookies seguros, HSTS, URLs).
        // Em local (HTTP / Laragon) o esquema não é forçado.
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('login-email-ip', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        Gate::define('admin', fn (User $user): bool => $user->isAdmin());

        View::composer('layout', function ($view) {
       
            $prompts = Auth::check()
                ? Prompt::query()->where('user_id', Auth::id())->latest()->limit(30)->get()
                : collect();

            $view->with('recentPrompts', $prompts);
        });
    }
}