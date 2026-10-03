<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\PasswordRules;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * A ordem importa: o TemplateSeeder associa os templates às linguagens,
     * frameworks e arquiteturas, então o catálogo precisa existir antes.
     *
     * Todo o seed usa updateOrCreate/firstOrCreate, então `php artisan db:seed`
     * pode ser executado quantas vezes for necessário sem duplicar registros
     * nem estourar as chaves únicas de `users.email` e dos slugs.
     *
     * Em production o catálogo pode ser carregado, mas usuários de
     * demonstração NÃO são criados. O admin inicial sai de
     * `php artisan gueass:create-admin`.
     */
    public function run(): void
    {
        $this->call([
            ArchitectureSeeder::class,
            LanguageSeeder::class,
            TemplateSeeder::class,
        ]);

        if (app()->environment('production')) {
            return;
        }

        $this->seedDemoUser(
            env('DEMO_ADMIN_EMAIL', 'admin@email.com'),
            'Administrador',
            'ADM',
            env('DEMO_ADMIN_PASSWORD'),
            'administrador de demonstração'
        );

        $this->seedDemoUser(
            env('DEMO_USER_EMAIL', 'usuario@email.com'),
            'Usuario Teste',
            'USU',
            env('DEMO_USER_PASSWORD'),
            'usuário de demonstração'
        );
    }

    private function seedDemoUser(
        string $email,
        string $name,
        string $role,
        ?string $password,
        string $label
    ): void {
        $plain = filled($password) ? $password : Str::password(16);

        try {
            Validator::make(
                [
                    'password' => $plain,
                    'password_confirmation' => $plain,
                ],
                ['password' => PasswordRules::required()]
            )->validate();
        } catch (ValidationException $e) {
            throw new \RuntimeException(
                "A senha do {$label} não atende à política (mínimo 8 caracteres, letras e números)."
            );
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $plain,
            ]
        );
        $user->forceFill(['role' => $role])->save();

        if (! filled($password)) {
            $this->command?->info("Senha do {$label} ({$email}), exibida uma única vez: {$plain}");
        }
    }
}
