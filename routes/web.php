<?php

use App\Http\Controllers\ArchitectureController;
use App\Http\Controllers\FrameworkController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Rotas de Autenticação (Abertas)
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register'])->name('register');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');


// ==========================================
// Rotas de Visualização (Acessíveis por Ambos)
// ==========================================
// Liberamos o método 'index' para que tanto ADM quanto USU consigam listar os registros nas telas
Route::get('/languages', [LanguageController::class, 'index'])->name('languages.index');
Route::get('/frameworks', [FrameworkController::class, 'index'])->name('frameworks.index');
Route::get('/architectures', [ArchitectureController::class, 'index'])->name('architectures.index');
Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');


// ==========================================
// NOTA: Rotas Compartilhadas (Acessíveis por ADM e USU)
// ==========================================
// Usamos o middleware 'auth' padrão do Laravel para garantir que qualquer usuário logado acesse a Home e gere prompts
Route::middleware(['auth'])->group(function () {
    Route::get('/', [PromptController::class, 'index'])->name('home');
    Route::post('/prompts/generate', [PromptController::class, 'generate'])->name('prompts.generate');
    Route::get('/prompts/{prompt}', [PromptController::class, 'show'])->name('prompts.show');
    Route::delete('/prompts/{prompt}', [PromptController::class, 'destroy'])->name('prompts.destroy');

    Route::get('/api/languages/{language}/frameworks', function (\App\Models\Language $language) {
        return response()->json($language->frameworks);
    })->name('api.languages.frameworks');
});


// ==========================================
// Rotas do Administrador Exclusivas (role.adm)
// ==========================================
Route::middleware(['role.adm'])->group(function () {
    Route::get('/admin', function () {
        return redirect()->route('languages.index');
    });

    // Recursos administrativos protegidos (usando 'except' para não duplicar o index declarado acima)
    Route::resource('languages', LanguageController::class)->except(['index']);
    Route::resource('frameworks', FrameworkController::class)->except(['index']);
    Route::resource('architectures', ArchitectureController::class)->except(['index']);
    Route::resource('templates', TemplateController::class)->except(['index']);
});


// ==========================================
// Rotas de Debug Local
// ==========================================
if (app()->environment('local')) {
    Route::get('/debug/generate-sample', function (App\Services\PromptGenerator $promptGenerator) {
        $template = App\Models\Template::query()->where('is_active', true)->first();
        if (! $template) {
            return response('Nenhum template ativo encontrado.', 404);
        }

        $architecture = App\Models\Architecture::query()->first();
        if (! $architecture) {
            return response('Nenhuma arquitetura encontrada.', 404);
        }

        $language = App\Models\Language::query()->first();
        if (! $language) {
            return response('Nenhuma linguagem encontrada.', 404);
        }

        $framework = null; // testa sem framework
        $output = $promptGenerator->render($template, 'Exemplo de intenção para teste', $language, $architecture, $framework);

        return response(nl2br(e($output)));
    });
}
