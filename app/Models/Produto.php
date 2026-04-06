<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Produto extends Model
{
    protected $table = 'produtos';

    public $incrementing = true;

    protected $fillable = [
        'nome',
        'descricao',
        'preco',
        'estoque',
        'categoria_id'
    ];

    public function categoria(){
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }
}