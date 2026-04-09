<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Framework extends Model
{
    use HasFactory;

   protected $fillable = [
        'nome',
        'slug', 
        'language_id'
    ];

    public function language()
    {
        return $this->belongsTo(Language::class);
    }
}