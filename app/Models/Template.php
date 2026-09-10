<?php

namespace App\Models;

use App\Services\AI\TemplateInterpolator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Template extends Model
{
    protected $table = 'templates';

    public const BLOCO_A = 'A';

    public const BLOCO_B = 'B';

    public const BLOCO_C = 'C';

    public const BLOCOS = [
        self::BLOCO_A => 'Features / Funcionalidades',
        self::BLOCO_B => 'Raciocínio / Lógica',
        self::BLOCO_C => 'Análise / Etapa 0',
    ];

    protected $fillable = [
        'nome',
        'slug',
        'descricao',
        'intent_type',
        'bloco',
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

    /**
     * Marcadores do corpo que o usuário precisa preencher na tela de geração:
     * tudo o que o pipeline não deriva sozinho da intenção.
     *
     * @return array<int, string>
     */
    public function dynamicVariables(): array
    {
        return (new TemplateInterpolator)->extractVariables((string) $this->corpo_template);
    }

    /**
     * Rótulo do campo no formulário: NOME_DA_ENTIDADE vira "Nome da entidade".
     */
    public static function variableLabel(string $variable): string
    {
        return Str::ucfirst(Str::lower(str_replace('_', ' ', trim($variable))));
    }

    public function resolveBloco(): string
    {
        $bloco = strtoupper((string) $this->bloco);

        if (isset(self::BLOCOS[$bloco])) {
            return $bloco;
        }

        if (preg_match('/\(([ABC])\d+\)/u', (string) $this->nome, $matches)) {
            return $matches[1];
        }

        return match ($this->intent_type) {
            'analysis' => self::BLOCO_C,
            'refactor' => self::BLOCO_B,
            default => self::BLOCO_A,
        };
    }

    public function blocoLabel(): string
    {
        return self::BLOCOS[$this->resolveBloco()];
    }

    public function blocoShortLabel(): string
    {
        return match ($this->resolveBloco()) {
            self::BLOCO_B => 'Raciocínio',
            self::BLOCO_C => 'Análise',
            default => 'Features',
        };
    }

    public function blocoBadgeClasses(): string
    {
        return match ($this->resolveBloco()) {
            self::BLOCO_B => 'bg-amber-100 text-amber-800 dark:bg-amber-900/70 dark:text-amber-100',
            self::BLOCO_C => 'bg-teal-100 text-teal-800 dark:bg-teal-900/70 dark:text-teal-100',
            default => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/70 dark:text-indigo-100',
        };
    }
}
