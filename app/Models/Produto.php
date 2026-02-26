<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Produto extends Model
{
    protected $table = "produtos";

    public $incrementing = true;

    protected $fillable = [
        'name',
        'descricao',
        'preco',
        'estoque',
        'categoria_id'
    ];

    protected $casts = [
        'preco' => 'decimal:2',
        'estoque' => 'integer'
    ];

    public function categoria(){
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

}
