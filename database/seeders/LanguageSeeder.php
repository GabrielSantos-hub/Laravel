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
            ],
        ],
        'JavaScript' => [
            'slug' => 'javascript',
            'frameworks' => [
                'React' => 'react',
                'Vue.js' => 'vue-js',
                'Node.js' => 'node-js',
            ],
        ],
        'TypeScript' => [
            'slug' => 'typescript',
            'frameworks' => [
                'Angular' => 'angular',
                'NestJS' => 'nestjs',
            ],
        ],
        'Python' => [
            'slug' => 'python',
            'frameworks' => [
                'Django' => 'django',
                'FastAPI' => 'fastapi',
            ],
        ],
        'Java' => [
            'slug' => 'java',
            'frameworks' => [
                'Spring' => 'spring',
            ],
        ],
        'C#' => [
            'slug' => 'csharp',
            'frameworks' => [
                '.NET' => 'dotnet',
            ],
        ],
        'Go' => [
            'slug' => 'golang',
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
