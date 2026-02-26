<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pedidos extends Model
{
    protected $table = "pedidos";
    

    protected $casts = [
        'total' => 'decimal: 2'
    ];

    public function item (){
        return ;
    }

}
