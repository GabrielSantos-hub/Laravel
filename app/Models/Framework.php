<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Framework extends Model
{
    protected $table = 'frameworks';

    protected $fillable = ['nome', 'language_id'];

    // Relacionamento Inverso: Um framework pertence a uma linguagem
    public function language()
    {
        return $this->belongsTo(Language::class, 'language_id');
    }
}