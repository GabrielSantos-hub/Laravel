<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemPedido extends Model
{
    protected $table = "itens_pedido";

    public $incrementing = true;
    
    protected $fillable = [
        'pedido_id',
        'produto_id',
        'quantidade',
        'preco'
    ];

    protected $casts = [
        'quantidade' => 'integer',
        'preco' => 'decimal: 2'
    ];

    public function pedido (){
        return $this->belongsTo(Pedido::class, 'pedido_id'); 
    }

    public function pruduto () {
        return $this->belongsTo(Produto::class, 'produto_id');
    }

}
