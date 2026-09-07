<?php

namespace Database\Seeders;

use App\Models\Architecture;
use Illuminate\Database\Seeder;

/**
 * Os nomes aqui são exatamente os rótulos que o NullAIProvider devolve em
 * `architecture`. É isso que permite ao TemplateSelector resolver a arquitetura
 * deduzida do texto para um registro real do catálogo.
 */
class ArchitectureSeeder extends Seeder
{
    public function run(): void
    {
        $arquiteturas = [
            'Clean Architecture' => 'Camadas concêntricas com o domínio no centro, independente de framework e de banco.',
            'Hexagonal' => 'Portas e adaptadores: o núcleo da aplicação conversa com o mundo externo por interfaces.',
            'DDD' => 'Domain-Driven Design: modelagem guiada pela linguagem e pelas regras do domínio.',
            'CQRS' => 'Separação entre o caminho de escrita (comandos) e o de leitura (consultas).',
            'Event Driven' => 'Componentes desacoplados que se comunicam pela publicação e consumo de eventos.',
            'Microservices' => 'Serviços pequenos e independentes, com deploy e banco próprios.',
            'Serverless' => 'Funções sob demanda, sem gerenciamento de servidor pela aplicação.',
            'Monolith' => 'Aplicação única, com todos os módulos no mesmo processo e no mesmo deploy.',
            'MVC' => 'Model-View-Controller: separação entre dados, apresentação e coordenação da requisição.',
            'Layered' => 'Camadas horizontais empilhadas: apresentação, aplicação, domínio e infraestrutura.',
        ];

        foreach ($arquiteturas as $nome => $descricao) {
            Architecture::query()->updateOrCreate(['nome' => $nome], ['descricao' => $descricao]);
        }
    }
}

// para rodar o seeder: php artisan db:seed --class=ArchitectureSeeder
// popular banco como um atalho do INSERT INTO
