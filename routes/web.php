<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\TemplateController;

Route::get('/', function () {
    return view('welcome');
});

// Rotas do nosso CRUD
Route::resource('languages', LanguageController::class);
// Quando você criar o controller de templates, a rota já está pronta aqui:
// Route::resource('templates', TemplateController::class);