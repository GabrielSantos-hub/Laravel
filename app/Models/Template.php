<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    protected $table = 'templates';

    protected $fillable = [
        'nome',
        'corpo_template',
        'versao',
        'is_active'
    ];

    // Converte o campo booleano do banco para true/false no PHP
    protected $casts = [
        'is_active' => 'boolean'
    ];
}
