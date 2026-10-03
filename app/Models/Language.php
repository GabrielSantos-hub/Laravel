<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Language extends Model
{
    protected $table = 'languages';

    protected $fillable = [
        'nome',
        'slug',
    ];

    public function frameworks()
    {
        return $this->hasMany(Framework::class, 'language_id');
    }

    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(Template::class);
    }
}
