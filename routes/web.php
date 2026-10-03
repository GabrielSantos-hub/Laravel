<?php

use App\Http\Controllers\AdminAuditLogController;
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
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware(['throttle:5,1', 'throttle:login-email-ip'])
        ->name('login.attempt');
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:10,1')
        ->name('register');
});

Route::get('/languages', [LanguageController::class, 'index'])->name('languages.index');
Route::get('/frameworks', [FrameworkController::class, 'index'])->name('frameworks.index');
Route::get('/architectures', [ArchitectureController::class, 'index'])->name('architectures.index');
Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
Route::view('/privacidade', 'privacy')->name('privacidade');

Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/senha-obrigatoria', [ProfileController::class, 'editForcedPassword'])->name('password.forced.edit');
    Route::put('/senha-obrigatoria', [ProfileController::class, 'updateForcedPassword'])
        ->middleware('throttle:10,1')
        ->name('password.forced.update');
});

Route::middleware(['auth', 'password.changed'])->group(function () {
    Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/perfil', [ProfileController::class, 'update'])
        ->middleware('throttle:20,1')
        ->name('profile.update');
    Route::post('/perfil/avatar', [ProfileController::class, 'updateAvatar'])
        ->middleware('throttle:10,1')
        ->name('profile.avatar');
    Route::delete('/perfil', [ProfileController::class, 'destroy'])
        ->middleware('throttle:5,1')
        ->name('profile.destroy');

    Route::get('/', [PromptController::class, 'index'])->name('home');
    Route::post('/prompts/generate', [PromptController::class, 'generate'])
        ->middleware('throttle:10,1')
        ->name('prompts.generate');
    Route::get('/prompts/{prompt}', [PromptController::class, 'show'])->name('prompts.show');
    Route::post('/prompts/{prompt}/feedback', [PromptController::class, 'feedback'])
        ->middleware('throttle:20,1')
        ->name('prompts.feedback');
    Route::delete('/prompts/{prompt}', [PromptController::class, 'destroy'])
        ->middleware('throttle:20,1')
        ->name('prompts.destroy');

    Route::get('/api/languages/{language}/frameworks', function (\App\Models\Language $language) {
        return response()->json($language->frameworks);
    })->name('api.languages.frameworks');
});

Route::middleware(['auth', 'password.changed', 'can:admin'])->group(function () {
    Route::get('/admin', function () {
        return redirect()->route('admin.dashboard');
    });
    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/admin/auditoria', [AdminAuditLogController::class, 'index'])->name('admin.audit.index');
    Route::get('/admin/users', [AdminUserController::class, 'index'])->name('admin.users.index');
    Route::put('/admin/users/{user}/password', [AdminUserController::class, 'resetPassword'])
        ->middleware('throttle:10,1')
        ->name('admin.users.password');

    Route::resource('languages', LanguageController::class)->except(['index'])->middleware('throttle:20,1');
    Route::resource('frameworks', FrameworkController::class)->except(['index', 'show'])->middleware('throttle:20,1');
    Route::resource('architectures', ArchitectureController::class)->except(['index', 'show'])->middleware('throttle:20,1');
    Route::resource('templates', TemplateController::class)->except(['index', 'show'])->middleware('throttle:20,1');
});
