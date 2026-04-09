<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    // Informa qual tabela este model gerencia
    protected $table = 'languages';

    // Segurança: Quais campos podem ser preenchidos pelos formulários
    protected $fillable = [
        'nome',
        'slug'
    ];

    // Relacionamento: Uma linguagem tem vários frameworks
    public function frameworks()
    {
        return $this->hasMany(Framework::class, 'language_id');
    }
}