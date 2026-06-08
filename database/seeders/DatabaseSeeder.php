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
        User::create([
            'name' => 'Administrador',
            'email' => 'admin@email.com', 
            'password' => Hash::make('2133@JJ#Asfd'), 
            'role' => 'ADM',
        ]);
        
        User::create([
            'name' => 'Usuario Teste',
            'email' => 'usuario@email.com',
            'password' => Hash::make('user123'),
            'role' => 'USU',
        ]);
    }
}