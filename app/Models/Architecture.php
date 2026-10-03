<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Architecture extends Model
{
    protected $table = 'architectures';

    protected $fillable = [
        'nome',
        'descricao',
    ];

    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(Template::class);
    }
}
