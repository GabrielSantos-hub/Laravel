<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

class PasswordRules
{
    /**
     * Política compartilhada: 8+ caracteres com letras e números.
     * uncompromised() só em production; o verificador do Laravel já
     * ignora falha de rede (não bloqueia o cadastro se a API cair).
     */
    public static function policy(): Password
    {
        $rule = Password::min(8)->letters()->numbers();

        if (app()->environment('production')) {
            $rule->uncompromised();
        }

        return $rule;
    }

    /**
     * @return list<mixed>
     */
    public static function required(): array
    {
        return ['required', 'string', 'confirmed', self::policy()];
    }

    /**
     * @return list<mixed>
     */
    public static function optional(): array
    {
        return ['nullable', 'string', 'confirmed', self::policy()];
    }
}
