<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    protected $table = 'templates';

    protected $fillable = [
        'nome',
        'corpo_template',
        'versao',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Template is now architecture-agnostic; relationship removed.

    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class);
    }
}
