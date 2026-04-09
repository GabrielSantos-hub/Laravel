<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\FrameworkController;
use App\Http\Controllers\TemplateController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Página Inicial
Route::get('/', function () {
    return view('welcome');
});

/**
 * Rotas de CRUD
 * O comando resource cria automaticamente as 7 rotas padrão do Laravel:
 * index, create, store, show, edit, update, destroy
 */
Route::resource('languages', LanguageController::class);
Route::resource('frameworks', FrameworkController::class);

// Descomente esta linha quando o Controller de Templates estiver finalizado
// Route::resource('templates', TemplateController::class);