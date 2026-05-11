<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prompt extends Model
{
    protected $fillable = [
        'template_id',
        'architecture_id',
        'language_id',
        'framework_id',
        'input_text',
        'output_text',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function architecture(): BelongsTo
    {
        return $this->belongsTo(Architecture::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function framework(): BelongsTo
    {
        return $this->belongsTo(Framework::class);
    }
}
