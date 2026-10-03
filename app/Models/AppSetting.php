<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

    public static function getValue(string $key): ?string
    {
        $value = static::query()->where('key', $key)->value('value');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function putValue(string $key, ?string $value): void
    {
        if ($value === null) {
            static::query()->where('key', $key)->delete();

            return;
        }

        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }
};
