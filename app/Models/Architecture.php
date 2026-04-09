<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Architecture extends Model
{
    // Informa qual tabela este model gerencia
    protected $table = 'architectures';

    // Segurança: Quais campos podem ser preenchidos pelos formulários (Mass Assignment)
    protected $fillable = [
        'nome',
        'descricao'
    ];

    // Se no futuro você quiser relacionar a arquitetura a um template específico,
    // os métodos de relacionamento (hasMany / belongsTo) entrarão aqui.
}