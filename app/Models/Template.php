<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    protected $table = 'templates';

    protected $fillable = [
        'nome',
        'slug',
        'descricao',
        'intent_type',
        'corpo_template',
        'versao',
        'is_active',
        'is_generic',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_generic' => 'boolean',
    ];

    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class);
    }

    /**
     * Linguagens para as quais este template foi classificado. Um template sem
     * nenhuma linguagem associada é considerado genérico pelo TemplateSelector.
     */
    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(Language::class);
    }

    public function frameworks(): BelongsToMany
    {
        return $this->belongsToMany(Framework::class);
    }

    public function architectures(): BelongsToMany
    {
        return $this->belongsToMany(Architecture::class);
    }
}
