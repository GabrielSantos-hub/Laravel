<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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
     */
    public function run(): void
    {
        $this->call([
            ArchitectureSeeder::class,
            LanguageSeeder::class,
            TemplateSeeder::class,
        ]);

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@email.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('2133@JJ#Asfd'),
            ]
        );
        $admin->forceFill(['role' => 'ADM'])->save();

        User::query()->firstOrCreate(
            ['email' => 'usuario@email.com'],
            [
                'name' => 'Usuario Teste',
                'password' => Hash::make('user123'),
            ]
        );
    }
}
