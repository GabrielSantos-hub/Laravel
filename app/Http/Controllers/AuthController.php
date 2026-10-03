<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Security\SecurityLogger;
use App\Support\PasswordRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            app(SecurityLogger::class)->log('login_success', [
                'email' => $credentials['email'],
            ]);
            if (Auth::user()?->isAdmin()) {
                return redirect()->intended('/admin');
            }

            return redirect()->intended('/');
        }

        app(SecurityLogger::class)->log('login_failed', [
            'email' => $credentials['email'],
        ]);

        return back()->withErrors([
            'email' => 'As credenciais fornecidas não coincidem com os nossos registros.', 
        ])->onlyInput('email');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => PasswordRules::required(),
        ]);

        $validated['name'] = trim($validated['name']);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        Auth::login($user);
        app(SecurityLogger::class)->log('register', [
            'email' => $user->email,
            'user_id' => $user->id,
        ]);
        return redirect('/');
    }

    public function logout(Request $request)
    {
        app(SecurityLogger::class)->log('logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}