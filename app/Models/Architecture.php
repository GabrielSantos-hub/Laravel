<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Architecture extends Model
{
    // Informa qual tabela este model gerencia
    protected $table = 'architectures';

    //Quais campos podem ser preenchidos pelos formulários 
    protected $fillable = [
        'nome',
        'descricao'
    ];
}