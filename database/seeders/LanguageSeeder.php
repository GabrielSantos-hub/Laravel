<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Cria a Linguagem
        $php = \App\Models\Language::create(['nome' => 'PHP', 'slug' => 'php']);

        // Cria os Frameworks vinculados a ela
        $php->frameworks()->create(['nome' => 'Laravel', 'slug' => 'laravel']);
        $php->frameworks()->create(['nome' => 'Symfony', 'slug' => 'symfony']);

        // Outro exemplo
        $js = \App\Models\Language::create(['nome' => 'JavaScript', 'slug' => 'javascript']);
        $js->frameworks()->create(['nome' => 'React', 'slug' => 'react']);
    }
}
