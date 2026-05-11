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