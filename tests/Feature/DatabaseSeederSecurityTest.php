<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_em_local_cria_usuarios_de_demonstracao_sem_senha_literal_no_codigo(): void
    {
        $seeder = file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        $this->assertIsString($seeder);
        $this->assertStringNotContainsString('2133@JJ#Asfd', $seeder);
        $this->assertStringNotContainsString('user123', $seeder);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'admin@email.com', 'role' => 'ADM']);
        $this->assertDatabaseHas('users', ['email' => 'usuario@email.com', 'role' => 'USU']);
    }

    public function test_em_production_nao_cria_usuarios_de_demonstracao(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('users', ['email' => 'admin@email.com']);
        $this->assertDatabaseMissing('users', ['email' => 'usuario@email.com']);
        $this->assertSame(0, \App\Models\User::query()->count());
    }
}
