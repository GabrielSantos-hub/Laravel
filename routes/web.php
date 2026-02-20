<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controller\ExercicioController;


Route::get('/paginainicial', function () {
    return view('welcome');
});

Route::get('/exercicio', ['ExercicioController', 'exibirFormulario'] );
Route::post('/resposta',['ExercicioController', 'calcularSoma'] );

//rota para abrir o formulario do exercicio 2
Route::get('/exercicio2', );

//rota para receber os dados do formulario do exercicio 2
Route::post('/resposta2', );