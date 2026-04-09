<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ArchitectureController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\FrameworkController;
// use App\Http\Controllers\TemplateController;

// Página Inicial 
Route::get('/', function () {
    return view('welcome');
});

// Rotas de CRUD
Route::resource('languages', LanguageController::class);
Route::resource('frameworks', FrameworkController::class);
Route::resource('architectures', ArchitectureController::class);
// Route::resource('templates', TemplateController::class);