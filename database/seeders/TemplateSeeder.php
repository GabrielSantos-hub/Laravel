<?php

namespace Database\Seeders;

use App\Models\Architecture;
use App\Models\Framework;
use App\Models\Language;
use App\Models\Template;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * Templates já classificados nas tabelas pivô.
 *
 * A classificação é o que liga o catálogo ao TemplateSelector: template com
 * linguagem, framework ou arquitetura associados é pontuado pelas relações
 * (framework 6, arquitetura 5, linguagem 4); template sem nenhuma associação
 * cai no fallback textual, limitado a 3 pontos. Por isso o seeder classifica a
 * maioria dos templates e deixa poucos genéricos de propósito.
 *
 * Depende de LanguageSeeder e ArchitectureSeeder: as chaves usadas aqui são os
 * slugs das linguagens/frameworks e os nomes das arquiteturas de lá.
 */
class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $dados) {
            $template = Template::query()->updateOrCreate(
                ['nome' => $dados['nome']],
                [
                    'corpo_template' => $dados['corpo'],
                    'versao' => '1',
                    'is_active' => true,
                ]
            );

            // sync (e não attach) mantém o seeder idempotente: rodar duas vezes
            // não duplica nem deixa associação órfã de execuções anteriores.
            $template->languages()->sync($this->ids(Language::class, 'slug', $dados['languages']));
            $template->frameworks()->sync($this->ids(Framework::class, 'slug', $dados['frameworks']));
            $template->architectures()->sync($this->ids(Architecture::class, 'nome', $dados['architectures']));
        }
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<int, string>  $valores
     * @return array<int, int>
     */
    private function ids(string $model, string $coluna, array $valores): array
    {
        if ($valores === []) {
            return [];
        }

        return $model::query()->whereIn($coluna, $valores)->pluck('id')->all();
    }

    /**
     * @return array<int, array{
     *     nome: string,
     *     corpo: string,
     *     languages: array<int, string>,
     *     frameworks: array<int, string>,
     *     architectures: array<int, string>
     * }>
     */
    private function templates(): array
    {
        return [
            [
                'nome' => 'API REST em Laravel',
                'languages' => ['php'],
                'frameworks' => ['laravel'],
                'architectures' => ['Clean Architecture'],
                'corpo' => <<<'TXT'
                    Você é um engenheiro de software sênior especialista em {technologies}.

                    Objetivo: {objective}
                    {% if architecture %}Arquitetura de referência: {architecture}{% endif %}

                    Entregue, nesta ordem:
                    1. As rotas com verbo HTTP, URI e status de resposta.
                    2. O Controller, o FormRequest de validação e o Resource de saída.
                    3. As migrations e os Models com as relações necessárias.
                    4. Os testes de feature cobrindo o caminho de sucesso e os erros de validação.

                    {% if constraints %}Restrições obrigatórias:
                    {constraints}{% endif %}
                    TXT,
            ],
            [
                'nome' => 'Componente de Interface em Vue',
                'languages' => ['javascript'],
                'frameworks' => ['vue-js'],
                'architectures' => ['MVC'],
                'corpo' => <<<'TXT'
                    Você é um desenvolvedor front-end especialista em {technologies}.

                    Objetivo: {objective}

                    Entregue o componente com props e eventos tipados, os estados de carregamento e de erro, e a marcação acessível (rótulos, foco e navegação por teclado).

                    {% if constraints %}Restrições obrigatórias:
                    {constraints}{% endif %}
                    TXT,
            ],
            [
                'nome' => 'Microsserviço em Python',
                'languages' => ['python'],
                'frameworks' => ['django', 'fastapi'],
                'architectures' => ['Microservices'],
                'corpo' => <<<'TXT'
                    Você é um arquiteto de software especialista em {technologies}.

                    Objetivo: {objective}
                    {% if architecture %}Estilo arquitetural: {architecture}{% endif %}

                    Entregue o contrato da API, o modelo de dados do serviço, a estratégia de comunicação com os serviços vizinhos e o plano de observabilidade (logs, métricas e health check).

                    {% if constraints %}Restrições obrigatórias:
                    {constraints}{% endif %}
                    TXT,
            ],
            [
                'nome' => 'Serviço em Node com Portas e Adaptadores',
                'languages' => ['javascript', 'typescript'],
                'frameworks' => ['node-js', 'nestjs'],
                'architectures' => ['Hexagonal'],
                'corpo' => <<<'TXT'
                    Você é um engenheiro de back-end especialista em {technologies}.

                    Objetivo: {objective}
                    {% if architecture %}Arquitetura de referência: {architecture}{% endif %}

                    Separe explicitamente o núcleo de domínio dos adaptadores de entrada e saída, e mostre as interfaces (portas) que o núcleo expõe.

                    {% if constraints %}Restrições obrigatórias:
                    {constraints}{% endif %}
                    TXT,
            ],
            [
                'nome' => 'Correção de Bug',
                'languages' => ['php'],
                'frameworks' => ['laravel'],
                'architectures' => [],
                'corpo' => <<<'TXT'
                    Você é um desenvolvedor {language} experiente em depuração.

                    Problema relatado: {objective}

                    Antes de propor código, levante as hipóteses de causa raiz e diga como confirmar cada uma. Só então apresente a correção mínima, o teste que reproduz a falha e a verificação de que nada mais quebrou.

                    {% if constraints %}Restrições obrigatórias:
                    {constraints}{% endif %}
                    TXT,
            ],
            [
                'nome' => 'Testes Automatizados',
                'languages' => ['php'],
                'frameworks' => ['laravel'],
                'architectures' => [],
                'corpo' => <<<'TXT'
                    Você é um especialista em testes automatizados em {technologies}.

                    Objetivo: {objective}

                    Cubra o caminho de sucesso, os casos de borda e os cenários de erro. Use nomes de teste que descrevam o comportamento esperado e evite asserções redundantes.

                    {% if constraints %}Restrições obrigatórias:
                    {constraints}{% endif %}
                    TXT,
            ],
            [
                'nome' => 'Refatoração Guiada por Domínio',
                'languages' => ['php'],
                'frameworks' => ['laravel'],
                'architectures' => ['DDD'],
                'corpo' => <<<'TXT'
                    Você é um arquiteto de software especialista em {technologies}.

                    Objetivo da refatoração: {objective}
                    {% if architecture %}Direção arquitetural: {architecture}{% endif %}

                    Preserve o comportamento observável. Apresente os passos em incrementos pequenos, cada um com o teste que garante a equivalência antes e depois.

                    {% if constraints %}Restrições obrigatórias:
                    {constraints}{% endif %}
                    TXT,
            ],
            [
                'nome' => 'Documentação Técnica',
                'languages' => [],
                'frameworks' => [],
                'architectures' => [],
                'corpo' => <<<'TXT'
                    Você é um redator técnico.

                    Objetivo: {objective}
                    {% if technologies %}Contexto tecnológico: {technologies}{% endif %}

                    Escreva para quem chega ao projeto agora: comece pelo que a coisa faz, depois como instalar e usar, e só então os detalhes internos. Nada de parágrafo decorativo.

                    {% if constraints %}Restrições obrigatórias:
                    {constraints}{% endif %}
                    TXT,
            ],
            [
                'nome' => 'Prompt Genérico',
                'languages' => [],
                'frameworks' => [],
                'architectures' => [],
                'corpo' => <<<'TXT'
                    Você é um desenvolvedor sênior.

                    Objetivo: {objective}
                    {% if technologies %}Tecnologias envolvidas: {technologies}{% endif %}
                    {% if architecture %}Arquitetura: {architecture}{% endif %}

                    Explique a abordagem antes do código, entregue uma solução completa e aponte os pontos de atenção que restaram.

                    {% if constraints %}Restrições obrigatórias:
                    {constraints}{% endif %}
                    TXT,
            ],
        ];
    }
}
