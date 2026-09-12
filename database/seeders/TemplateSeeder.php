<?php

namespace Database\Seeders;

use App\Models\Architecture;
use App\Models\Framework;
use App\Models\Language;
use App\Models\Template;
use Illuminate\Database\Seeder;

/**
 * Biblioteca oficial de templates GUEASS v3.0.
 *
 * Os corpos usam `{chave}` e `{% if chave %}...{% endif %}` — a mesma sintaxe
 * do TemplateInterpolator. `intent_type` alimenta o seletor; `is_generic`
 * marca o fallback quando nenhum template específico pontua.
 */
class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // ==========================================
            // TEMPLATES DE CRIAÇÃO / IMPLEMENTAÇÃO
            // ==========================================
            [
                'name' => 'Role-Play & Restrição Absoluta (A1)',
                'slug' => 'roleplay-restricao-absoluta',
                'description' => 'Geração direta de código com persona sênior e mapeamento de requisitos implícitos de negócio.',
                'intent_type' => 'feature',
                'is_active' => true,
                'is_generic' => false,
                'body' => <<<'EOT'
Atue como um Engenheiro de Software Sênior{% if language %} especialista em {language}{% endif %}{% if framework %} e no framework {framework}{% endif %}.

Sua tarefa é criar uma solução de código{% if architecture %} seguindo estritamente a arquitetura {architecture}{% endif %} para o seguinte escopo de negócio:
{user_input}

Antes de exibir o código, faça uma análise analítica do problema e mapeie os requisitos implícitos do sistema (validações críticas, regras de segurança, controle de estado e dados necessários).

RESTRIÇÕES OBRIGATÓRIAS:
1. Escreva um código limpo, moderno, documentado apenas onde estritamente necessário e pronto para produção.
2. Siga rigorosamente as convenções de nomenclatura e boas práticas de segurança.
3. Não dê explicações genéricas ou introduções longas. Vá direto à estrutura de arquivos e ao código limpo.
EOT,
            ],
            [
                'name' => 'ICCE — Instrução, Contexto, Restrição, Exemplo (A2)',
                'slug' => 'icce-framework',
                'description' => 'Para funcionalidades específicas onde regras de negócio implícitas precisam se encaixar na arquitetura.',
                'intent_type' => 'feature',
                'is_active' => true,
                'is_generic' => false,
                'body' => <<<'EOT'
CONTEXTO:
Estou desenvolvendo um sistema corporativo{% if language %} utilizando {language}{% endif %}{% if framework %} com o framework {framework}{% endif %}{% if architecture %}, adotando o padrão {architecture}{% endif %}.
O foco do negócio é: {user_input}

INSTRUÇÃO:
1. Identifique e liste os principais casos de uso, regras de validação e entidades necessárias para cobrir este escopo de forma escalável.
2. Escreva o código completo e estruturado para implementar essa infraestrutura de negócio.

RESTRIÇÕES:
- Respeite a separação de conceitos e responsabilidades.
- Utilize recursos nativos da stack (validadores, ORM, middlewares de segurança e tratamento de exceções).

FORMATO DE SAÍDA:
Apresente a lista de componentes identificados para o problema, seguida apenas pelos blocos de código correspondentes e um guia de onde salvar cada arquivo.
EOT,
            ],
            [
                'name' => 'Few-Shot Estruturado & DDD (A3)',
                'slug' => 'few-shot-estruturado',
                'description' => 'Geração de arquitetura completa deduzindo entidades de domínio.',
                'intent_type' => 'architecture',
                'is_active' => true,
                'is_generic' => false,
                'body' => <<<'EOT'
Você é um especialista em arquitetura de software{% if architecture %} focado em {architecture}{% endif %}{% if language %} utilizando {language}{% endif %}{% if framework %} e {framework}{% endif %}.

Quando eu te passar um problema de negócio, você deve:
1. Deduzir as entidades lógicas e agregados do domínio necessários.
2. Estruturar a resposta separando os componentes em camadas claras.

Exemplo de formato esperado de saída:
- Camada de Dados/Entidades (Models/Migrations) → Código estruturado.
- Camada de Regras/Negócio (Services/Handlers/Domain) → Regras e validações completas.
- Camada de Entrada/Entrega (Controllers/Routes/APIs) → Manipulação limpa de requisições.

Solicitação do usuário:
{user_input}

Identifique o domínio completo da solicitação e escreva o código arquitetado seguindo a divisão do exemplo.
EOT,
            ],
            [
                'name' => 'Chain-of-Thought (CoT) — Pensamento em Cadeia (A4)',
                'slug' => 'chain-of-thought-cot',
                'description' => 'Para lógicas complexas onde falhar no fluxo de negócio arruína o software.',
                'intent_type' => 'feature',
                'is_active' => true,
                'is_generic' => false,
                'body' => <<<'EOT'
Analise o seguinte requisito de negócio{% if language %} para um sistema em {language}{% endif %}{% if framework %} + {framework}{% endif %}{% if architecture %} sob a arquitetura {architecture}{% endif %}:

Requisito:
{user_input}

Antes de escrever qualquer linha de código, faça uma análise mental dividida exatamente nestes passos:
1. ANÁLISE DE DOMÍNIO: Quais são as regras de negócio críticas, fluxos de exceção e alertas essenciais que esse sistema precisa na vida real?
2. MAPEAMENTO DE ARQUITETURA: Quais classes, interfaces ou tabelas precisam ser criadas?
3. OTIMIZAÇÃO: Quais design patterns ou funções nativas garantem performance e segurança para este caso?

Após apresentar essa análise estruturada, forneça o código final refatorado e completo.
EOT,
            ],
            [
                'name' => 'CRISPE Framework (B1)',
                'slug' => 'crispe-framework',
                'description' => 'Mapeamento de ponta a ponta com processo de execução passo a passo.',
                'intent_type' => 'feature',
                'is_active' => true,
                'is_generic' => false,
                'body' => <<<'EOT'
Context:
Desenvolvimento de uma solução de software{% if language %} em {language}{% endif %}{% if framework %} com {framework}{% endif %}{% if architecture %}, aplicando a arquitetura {architecture}{% endif %}.

Role:
Você é um Arquiteto de Software Sênior especialista em modelagem de sistemas.

Input:
{user_input}

Steps:
1. Realize o levantamento de requisitos funcionais ocultos e essenciais implícitos.
2. Identifique quais camadas gerenciarão cada regra encontrada.
3. Escreva o código limpo, desacoplado e completo para cada componente.
4. Mapeie a estrutura de diretórios sugerida para o projeto.

Parameters:
- Código DRY (Don't Repeat Yourself) e SOLID.
- Tratamento explícito de erros e edge cases do negócio.
- Proibido simplificações ou placeholders vazios (como "// implementar depois").
EOT,
            ],
            [
                'name' => 'ROLE com Rubrica de Avaliação (B2)',
                'slug' => 'role-rubrica-avaliacao',
                'description' => 'Garante a implementação de regras complexas através de auto-avaliação.',
                'intent_type' => 'feature',
                'is_active' => true,
                'is_generic' => false,
                'body' => <<<'EOT'
Role:
Você é um Tech Lead especializado{% if language %} em {language}{% endif %}{% if framework %} com {framework}{% endif %}{% if architecture %} sob a arquitetura {architecture}{% endif %}.

Objectives:
Mapeie o domínio completo e implemente uma solução escalável e pronta para produção para:
{user_input}

Limits:
- Pense em todas as necessidades de um sistema real (cadastros, logs, fluxos de erro, concorrência).
- Evite dependências desnecessárias; utilize a estrutura nativa da linguagem e do framework.

Evaluation (Critérios que o seu output DEVE cumprir para ser aprovado):
- O sistema gerado cobre todas as dores reais do contexto "{user_input}"?
- A separação de conceitos foi mantida intacta?
- As validações de dados e tratamento de exceções foram aplicados nas operações críticas?
EOT,
            ],
            [
                'name' => 'ReAct — Raciocínio + Ação (B3)',
                'slug' => 'react-raciocinio-acao',
                'description' => 'Descobre regras de negócio iterativamente no ciclo Thought/Action/Observation.',
                'intent_type' => 'feature',
                'is_active' => true,
                'is_generic' => false,
                'body' => <<<'EOT'
Você é um agente de raciocínio arquitetural especializado em transformar ideias brutas em código limpo{% if language %} usando {language}{% endif %}{% if framework %} e {framework}{% endif %}{% if architecture %} sob a arquitetura {architecture}{% endif %}.

Tarefa:
{user_input}

Instruções de Execução:
Descubra o que esse sistema precisa pensando passo a passo. Intercale seu processo usando a estrutura:
- Thought: [Análise de qual subsistema ou regra é necessária para complementar a ideia]
- Action: [Produção de um componente de código específico para resolver esse Thought]
- Observation: [Revisão se o componente criado é seguro, performático e se conecta com os outros]

Repita o ciclo até mapear e codificar o escopo completo. Ao final, consolide a estrutura.
EOT,
            ],
            [
                'name' => 'Chain-of-Verification / CoVe (B4)',
                'slug' => 'chain-of-verification-cove',
                'description' => 'Gera o código e faz uma auto-auditoria de segurança e regras antes da versão final.',
                'intent_type' => 'refactor',
                'is_active' => true,
                'is_generic' => false,
                'body' => <<<'EOT'
Você é um Engenheiro de Software e Auditor de Código Sênior{% if language %} especializado em {language}{% endif %}{% if framework %}, {framework}{% endif %}{% if architecture %} e {architecture}{% endif %}.

Tarefa:
{user_input}

Etapa 1 — ANÁLISE E CÓDIGO INICIAL:
Mapeie o ecossistema necessário para resolver "{user_input}" e escreva o código correspondente.

Etapa 2 — PERGUNTAS DE VERIFICAÇÃO:
Faça a si mesmo as seguintes perguntas de auditoria:
- "Esqueci de incluir alguma regra vital ou edge case para este cenário?"
- "O código possui brechas de segurança, vazamentos de memória ou validações fracas?"
- "A separação de camadas está respeitada?"

Etapa 3 — RESPOSTA REVISADA:
Com base nas falhas encontradas na Etapa 2, reescreva a solução entregando o código corrigido e funcional.
EOT,
            ],
            [
                'name' => 'Self-Consistency — Auto-Consistência (B5)',
                'slug' => 'self-consistency',
                'description' => 'Compara 3 visões de arquitetura/modelagem e escolhe a mais robusta.',
                'intent_type' => 'architecture',
                'is_active' => true,
                'is_generic' => false,
                'body' => <<<'EOT'
Você é um Arquiteto de Software sênior{% if language %} especializado em {language}{% endif %}{% if framework %} com {framework}{% endif %}{% if architecture %} sob a arquitetura {architecture}{% endif %}.

Problema de Negócio:
{user_input}

Gere 3 visões ou abordagens diferentes sobre como modelar as funcionalidades e regras de negócio para resolver este problema.

Para cada abordagem, apresente:
- O foco da estratégia (ex: Abordagem 1: Alta Segurança e Logs / Abordagem 2: Performance e Cache / Abordagem 3: Simplicidade).
- Como o escopo de "{user_input}" seria desmembrado nessa visão.
- Prós e Contras arquiteturais.

Ao final, compare-as, escolha a mais robusta para um software comercial real e gere o código completo dela.
EOT,
            ],
            [
                'name' => 'RTF Cirúrgico — Role, Task, Format (B6)',
                'slug' => 'rtf-cirurgico',
                'description' => 'Geração rápida e direta de código sem explicações conversacionais.',
                'intent_type' => 'feature',
                'is_active' => true,
                'is_generic' => false,
                'body' => <<<'EOT'
Role:
Você é um Programador Sênior focado em eficiência, segurança e clean code{% if language %} utilizando {language}{% endif %}{% if framework %} com {framework}{% endif %}{% if architecture %} e {architecture}{% endif %}.

Task:
Mapeie mentalmente os requisitos obrigatórios implícitos para o bom funcionamento de "{user_input}" e gere a lógica de código completa para o componente principal, tratando erros e validações.

Format:
Retorne APENAS o código com sintaxe destacada. Sem textos explicativos, sem introduções. Use comentários inline pontuais apenas para documentar decisões de regras de negócio complexas.
EOT,
            ],

            // ==========================================
            // NOVOS TEMPLATES: ANÁLISE / INSPEÇÃO (ETAPA 0)
            // ==========================================
            [
                'name' => 'Análise Estrutural & Code Review (C1)',
                'slug' => 'analise-estrutural-code-review',
                'description' => 'Auditoria de código, detecção de bugs, vulnerabilidades de segurança e violações de SOLID.',
                'intent_type' => 'analysis',
                'is_active' => true,
                'is_generic' => false,
                'body' => <<<'EOT'
Você é um Auditor de Código e Especialista em Segurança em Software{% if language %} focado em {language}{% endif %}{% if framework %} com {framework}{% endif %}.

Analise minuciosamente o código/projeto ou requisito fornecido a seguir:

ENTRADA PARA ANÁLISE:
{user_input}

Execute um diagnóstico técnico cobrindo os seguintes pontos:
1. **PONTOS CRÍTICOS & VULNERABILIDADES**: Identifique brechas de segurança (ex: SQL Injection, XSS, faltas de sanitização), gargalos de performance e bugs em potencial.
2. **ADERÊNCIA ARQUITETURAL**: Avalie se a organização de classes/módulos respeita as boas práticas{% if architecture %} do padrão {architecture}{% endif %} e os princípios SOLID.
3. **MÉTRICAS DE QUALIDADE**: Dê uma nota de 1 a 10 para Legibilidade, Manutenibilidade e Segurança.
4. **PLANO DE CORREÇÃO**: Apresente a versão refatorada e corrigida dos trechos mais problemáticos.
EOT,
            ],
            [
                'name' => 'Diagnóstico de Banco de Dados & Modelagem ERD (C2)',
                'slug' => 'diagnostico-banco-dados-erd',
                'description' => 'Auditoria de schemas de banco de dados, relacionamentos, chaves e normalização.',
                'intent_type' => 'analysis',
                'is_active' => true,
                'is_generic' => false,
                'body' => <<<'EOT'
Você é um DBA e Arquiteto de Dados Especialista.

Analise a proposta de modelagem de dados, documento ou código a seguir:

{user_input}

Forneça um relatório completo de modelagem contendo:
1. **AVALIAÇÃO DE NORMALIZAÇÃO**: Verifique se a estrutura atinge a 3ª Forma Normal (3FN). Aponta inconsistências de cardinalidade ou chaves estrangeiras ausentes.
2. **GARGALOS DE PERFORMANCE**: Indique quais tabelas precisarão de índices compostos e onde podem ocorrer problemas de query lenta (N+1, locks).
3. **SCHEMA RECOMENDADO**: Apresente as Migrations / DDL SQL otimizados e corrigidos para esta modelagem{% if framework %} compatíveis com as convenções do {framework}{% endif %}.
EOT,
            ],
            [
                'name' => 'Análise de Documentação & Requisitos de TCC (C3)',
                'slug' => 'analise-documentacao-tcc-ers',
                'description' => 'Valida Especificações de Requisitos (ERS/SRS) encontrando lacunas e regras não mapeadas.',
                'intent_type' => 'analysis',
                'is_active' => true,
                'is_generic' => false,
                'body' => <<<'EOT'
Você é um Engenheiro de Requisitos e Avaliador Acadêmico de Projetos de Software.

Analise o texto, documento ou especificação abaixo:

DOCUMENTO DE ENTRADA:
{user_input}

Sua missão é realizar um "Stress Test" funcional dessa especificação:
1. **RECURSOS E REGRAS IMPLÍCITAS FALTANTES**: O que o autor esqueceu de especificar? (Ex: fluxos de recuperação, regras de concorrência, cancelamentos, perfis de acesso).
2. **AMBIGUIDADES E RISCOS**: Destaque frases genéricas que podem gerar interpretação dúbia pelo desenvolvedor.
3. **CHECKLIST DE REQUISITOS (RF e RNF)**: Escreva uma tabela organizada com os Requisitos Funcionais (RFs) e Não-Funcionais (RNFs) derivados deste texto.
EOT,
            ],
            [
                'name' => 'Diagnóstico de Débito Técnico & Roadmap (C4)',
                'slug' => 'diagnostico-debito-tecnico',
                'description' => 'Mapeia código legado ou confuso e gera um plano de ação para refatoração.',
                'intent_type' => 'analysis',
                'is_active' => true,
                'is_generic' => false,
                'body' => <<<'EOT'
Atue como Engenheiro de Software responsável por modernização de sistemas legados.

Analise o código ou sistema descrito abaixo:
{user_input}

Forneça:
1. **DIAGNÓSTICO DE SMELLS**: Liste os Code Smells encontrados (God Object, Primitive Obsession, Coupling, etc.).
2. **PRIORIZAÇÃO DE IMPACTO**: Classifique as melhorias necessárias em Alto, Médio e Baixo risco.
3. **PASSO A PASSO DE REFATORAÇÃO**: Apresente a estratégia incremental (Boy Scout Rule) para refatorar este componente sem quebrar a aplicação{% if language %} em {language}{% endif %}.
EOT,
            ],

            // ==========================================
            // NOVOS TEMPLATES: INSPIRAÇÃO COMUNIDADE
            // ==========================================
            [
                'name' => 'C4 Model & System Design (D1)',
                'slug' => 'c4-model-system-design',
                'description' => 'Inspiração Reddit/SystemDesign: Desenha a arquitetura em alto nível antes do código.',
                'intent_type' => 'architecture',
                'is_active' => true,
                'is_generic' => false,
                'body' => <<<'EOT'
Você é um Arquiteto de Sistemas Distribuídos e especialista em System Design.

Dado o seguinte objetivo de software:
{user_input}

Projete a solução estruturada utilizando a metodologia C4 Model:
1. **NÍVEL 1: CONTEXTO**: Quem são os atores/usuários e quais sistemas externos interagem com essa solução?
2. **NÍVEL 2: ESPECIFICAÇÃO DE COMPONENTES**: Quais microsserviços, bancos de dados, filas (queues) e serviços de cache compõem a solução?
3. **DESENHO DE DIAGRAMA (Mermaid.js)**: Apresente um código de diagrama Mermaid renderizável representando o fluxo dos dados.
4. **TECNOLOGIAS SUGERIDAS**: Indique a melhor combinação{% if language %} de {language}{% endif %}{% if framework %} com {framework}{% endif %} e justificativas para cada escolha.
EOT,
            ],
            [
                'name' => 'API-First & OpenAPI Spec Generator (D2)',
                'slug' => 'api-first-openapi-generator',
                'description' => 'Inspiração Postman Community: Desenha o contrato OpenAPI 3.0/Swagger antes da implementação.',
                'intent_type' => 'documentation',
                'is_active' => true,
                'is_generic' => false,
                'body' => <<<'EOT'
Você é um Especialista em Design de APIs RESTful e Engenharia de Integrações.

Com base na intenção de negócio:
{user_input}

Crie a especificação completa do contrato dessa API:
1. **ESPECIFICAÇÃO YAML (OpenAPI 3.0)**: Endpoints, métodos HTTP corretos (GET, POST, PUT, DELETE), parâmetros de busca, payloads de requisição e respostas de status (200, 201, 400, 401, 422, 500).
2. **ESTRUTURA DE DADOS**: DTOs e Schemas organizados de entrada e saída.
3. **CÓDIGO BASE**: Mostre como declarar os Controllers/Rotas{% if framework %} no {framework}{% endif %}{% if language %} em {language}{% endif %} para atender estritamente a este contrato.
EOT,
            ],

            // ==========================================
            // TEMPLATE CORINGA / FALLBACK OBRIGATÓRIO
            // ==========================================
            [
                'name' => 'Desenvolvimento Geral & Assistente de Prompt (Fallback)',
                'slug' => 'desenvolvimento-geral-fallback',
                'description' => 'Template genérico ativado quando a descrição do usuário for aberta ou não bater com tags específicas.',
                'intent_type' => 'generic',
                'is_active' => true,
                'is_generic' => true,
                'body' => <<<'EOT'
Você é um Engenheiro de Software Sênior e Especialista em Soluções Tecnológicas.

O usuário solicita auxílio para a seguinte demanda:
{user_input}

INSTRUÇÕES DE EXECUÇÃO:
1. Analise o objetivo da solicitação e identifique as melhores práticas e padrões de mercado indicados para este cenário{% if language %} em {language}{% endif %}{% if framework %} usando {framework}{% endif %}.
2. Se o pedido for a criação de código, forneça uma implementação modular, limpa e segura.
3. Se o pedido for uma dúvida ou análise, responda de forma clara, objetiva e estruturada em tópicos técnicos.
4. Destaque alertas de segurança ou validações essenciais para este caso.
EOT,
            ],
        ];

        $slugsOficiais = [];

        foreach ($templates as $data) {
            $slugsOficiais[] = $data['slug'];

            Template::query()->updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'nome' => $data['name'],
                    'descricao' => $data['description'],
                    'corpo_template' => $data['body'],
                    'intent_type' => $data['intent_type'],
                    'bloco' => $this->resolverBloco($data['name']),
                    'is_active' => $data['is_active'],
                    'is_generic' => $data['is_generic'],
                    'versao' => '3.0',
                ]
            );
        }

        // A biblioteca oficial substitui os templates artesanais das etapas
        // anteriores: desativa o que não está no catálogo v3 para não competir
        // na pontuação do seletor.
        Template::query()
            ->where(function ($query) use ($slugsOficiais) {
                $query->whereNull('slug')->orWhereNotIn('slug', $slugsOficiais);
            })
            ->update(['is_active' => false]);

        $this->vincularPivots();
    }

    private function resolverBloco(string $name): string
    {
        if (preg_match('/\(([ABC])\d+\)/u', $name, $matches)) {
            return $matches[1];
        }

        return Template::BLOCO_A;
    }

    private function vincularPivots(): void
    {
        $csharp = Language::query()->where('nome', 'C#')->first();
        $dotnet = Framework::query()->where('nome', '.NET')->first();
        $clean = Architecture::query()->where('nome', 'Clean Architecture')->first();

        // O Roleplay não pode monopolizar PHP/Laravel: isso fazia toda
        // intenção válida cair no Template #1 por pontuação de stack.
        $roleplay = Template::query()->where('slug', 'roleplay-restricao-absoluta')->first();
        if ($roleplay) {
            $roleplay->languages()->sync([]);
            $roleplay->frameworks()->sync([]);
            $roleplay->architectures()->sync([]);
        }

        $review = Template::query()->where('slug', 'analise-estrutural-code-review')->first();
        if ($review) {
            $review->languages()->sync($csharp ? [$csharp->id] : []);
            $review->frameworks()->sync($dotnet ? [$dotnet->id] : []);
            $review->architectures()->sync($clean ? [$clean->id] : []);
        }
    }
}
