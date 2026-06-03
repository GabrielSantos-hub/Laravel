<?php

use App\Http\Controllers\ArchitectureController;
use App\Http\Controllers\FrameworkController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\TemplateController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PromptController::class, 'index'])->name('home');

Route::post('/prompts/generate', [PromptController::class, 'generate'])->name('prompts.generate');
Route::get('/prompts/{prompt}', [PromptController::class, 'show'])->name('prompts.show');
Route::delete('/prompts/{prompt}', [PromptController::class, 'destroy'])->name('prompts.destroy');

Route::resource('languages', LanguageController::class);
Route::resource('frameworks', FrameworkController::class);
Route::resource('architectures', ArchitectureController::class);
Route::resource('templates', TemplateController::class);

// Rota da API para buscar os frameworks vinculados a uma linguagem
Route::get('/api/languages/{language}/frameworks', function (\App\Models\Language $language) {
	return response()->json($language->frameworks);
})->name('api.languages.frameworks');

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
