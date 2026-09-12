<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\ArchitectureController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FrameworkController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\TemplateController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:10,1')
        ->name('register');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/languages', [LanguageController::class, 'index'])->name('languages.index');
Route::get('/frameworks', [FrameworkController::class, 'index'])->name('frameworks.index');
Route::get('/architectures', [ArchitectureController::class, 'index'])->name('architectures.index');
Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
Route::view('/privacidade', 'privacy')->name('privacidade');

Route::middleware(['auth'])->group(function () {
    Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/perfil', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/perfil/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');

    Route::get('/', [PromptController::class, 'index'])->name('home');
    Route::post('/prompts/generate', [PromptController::class, 'generate'])
        ->middleware('throttle:10,1')
        ->name('prompts.generate');
    Route::get('/prompts/{prompt}', [PromptController::class, 'show'])->name('prompts.show');
    Route::post('/prompts/{prompt}/feedback', [PromptController::class, 'feedback'])->name('prompts.feedback');
    Route::delete('/prompts/{prompt}', [PromptController::class, 'destroy'])->name('prompts.destroy');

    Route::get('/api/languages/{language}/frameworks', function (\App\Models\Language $language) {
        return response()->json($language->frameworks);
    })->name('api.languages.frameworks');
});

Route::middleware(['auth', 'can:admin'])->group(function () {
    Route::get('/admin', function () {
        return redirect()->route('admin.dashboard');
    });
    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/admin/users', [AdminUserController::class, 'index'])->name('admin.users.index');
    Route::put('/admin/users/{user}/password', [AdminUserController::class, 'resetPassword'])->name('admin.users.password');

    Route::resource('languages', LanguageController::class)->except(['index']);
    Route::resource('frameworks', FrameworkController::class)->except(['index', 'show']);
    Route::resource('architectures', ArchitectureController::class)->except(['index', 'show']);
    Route::resource('templates', TemplateController::class)->except(['index', 'show']);
});
