<?php

namespace Database\Seeders;

use App\Models\Framework;
use App\Models\Language;
use Illuminate\Database\Seeder;

/**
 * Catálogo de linguagens e frameworks.
 *
 * Os nomes acompanham os rótulos do NullAIProvider (`Vue.js`, `Node.js`,
 * `.NET`...) porque o TemplateSelector casa a tecnologia deduzida do texto
 * contra `nome` e `slug` normalizados. Nome divergente aqui significa
 * tecnologia que a seleção automática não consegue resolver.
 */
class LanguageSeeder extends Seeder
{
    /**
     * @var array<string, array{slug: string, frameworks: array<string, string>}>
     */
    private const CATALOGO = [
        'PHP' => [
            'slug' => 'php',
            'frameworks' => [
                'Laravel' => 'laravel',
                'Symfony' => 'symfony',
                'Livewire' => 'livewire',
                'CodeIgniter' => 'codeigniter',
            ],
        ],
        'JavaScript' => [
            'slug' => 'javascript',
            'frameworks' => [
                'React' => 'react',
                'Vue.js' => 'vue-js',
                'Node.js' => 'node-js',
                'Express' => 'express',
                'Svelte' => 'svelte',
                'Nuxt' => 'nuxt',
            ],
        ],
        'TypeScript' => [
            'slug' => 'typescript',
            'frameworks' => [
                'Angular' => 'angular',
                'NestJS' => 'nestjs',
                'Next.js' => 'next-js',
                'Express com TypeScript' => 'express-typescript',
            ],
        ],
        'Python' => [
            'slug' => 'python',
            'frameworks' => [
                'Django' => 'django',
                'FastAPI' => 'fastapi',
                'Flask' => 'flask',
            ],
        ],
        'Java' => [
            'slug' => 'java',
            'frameworks' => [
                'Spring' => 'spring',
                'Quarkus' => 'quarkus',
            ],
        ],
        'C#' => [
            'slug' => 'csharp',
            'frameworks' => [
                '.NET' => 'dotnet',
                'Blazor' => 'blazor',
            ],
        ],
        'Go' => [
            'slug' => 'golang',
            'frameworks' => [
                'Gin' => 'gin',
                'Fiber' => 'fiber',
            ],
        ],
        'Kotlin' => [
            'slug' => 'kotlin',
            'frameworks' => [
                'Ktor' => 'ktor',
                'Jetpack Compose' => 'jetpack-compose',
            ],
        ],
        'Swift' => [
            'slug' => 'swift',
            'frameworks' => [
                'SwiftUI' => 'swiftui',
                'Vapor' => 'vapor',
            ],
        ],
        'Rust' => [
            'slug' => 'rust',
            'frameworks' => [
                'Axum' => 'axum',
                'Actix Web' => 'actix-web',
            ],
        ],
        'Ruby' => [
            'slug' => 'ruby',
            'frameworks' => [
                'Ruby on Rails' => 'ruby-on-rails',
                'Sinatra' => 'sinatra',
            ],
        ],
        'Dart' => [
            'slug' => 'dart',
            'frameworks' => [
                'Flutter' => 'flutter',
            ],
        ],
        'SQL' => [
            'slug' => 'sql',
            'frameworks' => [],
        ],
        'C++' => [
            'slug' => 'cpp',
            'frameworks' => [],
        ],
        'C' => [
            'slug' => 'c',
            'frameworks' => [],
        ],
    ];

    public function run(): void
    {
        foreach (self::CATALOGO as $nome => $dados) {
            $language = Language::query()->updateOrCreate(
                ['slug' => $dados['slug']],
                ['nome' => $nome]
            );

            foreach ($dados['frameworks'] as $frameworkNome => $frameworkSlug) {
                Framework::query()->updateOrCreate(
                    ['slug' => $frameworkSlug],
                    ['nome' => $frameworkNome, 'language_id' => $language->id]
                );
            }
        }
    }
}
