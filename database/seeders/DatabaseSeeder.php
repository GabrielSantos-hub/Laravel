<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Cria o usuário Administrador do sistema
        User::create([
            'name' => 'Administrador',
            'email' => 'admin@email.com', // <- Coloque o e-mail que você desejar usar
            'password' => Hash::make('bielsan123'), // <- Defina a sua senha aqui
            'role' => 'ADM',
        ]);
        
        // Opcional: Se quiser criar um usuário comum de testes também:
        User::create([
            'name' => 'Usuario Teste',
            'email' => 'usuario@email.com',
            'password' => Hash::make('user123'),
            'role' => 'USU',
        ]);
    }
}