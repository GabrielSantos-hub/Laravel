<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Language extends Model
{
    // Informa qual tabela este model gerencia
    protected $table = 'languages';

    protected $fillable = [
        'nome',
        'slug',
    ];

    public function frameworks()
    {
        return $this->hasMany(Framework::class, 'language_id');  // Uma linguagem tem vários frameworks
    }

    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(Template::class);
    }
}
