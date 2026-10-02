<?php

namespace Tests\Feature;

use App\Models\Architecture;
use App\Models\Framework;
use App\Models\Language;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogReferentialIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_excluir_linguagem_nao_apaga_prompts_do_historico(): void
    {
        $admin = User::factory()->create(['role' => 'ADM']);
        $language = Language::query()->create(['nome' => 'Lua', 'slug' => 'lua']);
        $prompt = Prompt::query()->create([
            'user_id' => $admin->id,
            'language_id' => $language->id,
            'input_text' => 'Criar um script Lua.',
            'output_text' => 'Prompt gerado.',
        ]);

        $this->actingAs($admin)
            ->from(route('languages.index'))
            ->delete(route('languages.destroy', $language))
            ->assertRedirect(route('languages.index'))
            ->assertSessionHas('sucesso');

        $this->assertDatabaseMissing('languages', ['id' => $language->id]);
        $this->assertDatabaseHas('prompts', [
            'id' => $prompt->id,
            'language_id' => null,
            'output_text' => 'Prompt gerado.',
        ]);
    }

    public function test_nao_exclui_linguagem_vinculada_a_framework(): void
    {
        $admin = User::factory()->create(['role' => 'ADM']);
        $language = Language::query()->create(['nome' => 'Go', 'slug' => 'go']);
        Framework::query()->create([
            'nome' => 'Gin',
            'slug' => 'gin',
            'language_id' => $language->id,
        ]);

        $this->actingAs($admin)
            ->from(route('languages.index'))
            ->delete(route('languages.destroy', $language))
            ->assertRedirect(route('languages.index'))
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('languages', ['id' => $language->id]);
        $this->assertDatabaseHas('frameworks', ['slug' => 'gin']);
    }

    public function test_excluir_arquitetura_nao_apaga_prompts_do_historico(): void
    {
        $admin = User::factory()->create(['role' => 'ADM']);
        $architecture = Architecture::query()->create([
            'nome' => 'Hexagonal',
            'descricao' => 'Portas e adaptadores',
        ]);
        $prompt = Prompt::query()->create([
            'user_id' => $admin->id,
            'architecture_id' => $architecture->id,
            'input_text' => 'Criar serviço hexagonal.',
            'output_text' => 'Prompt gerado.',
        ]);

        $this->actingAs($admin)
            ->from(route('architectures.index'))
            ->delete(route('architectures.destroy', $architecture))
            ->assertRedirect(route('architectures.index'))
            ->assertSessionHas('sucesso');

        $this->assertDatabaseMissing('architectures', ['id' => $architecture->id]);
        $this->assertDatabaseHas('prompts', [
            'id' => $prompt->id,
            'architecture_id' => null,
            'output_text' => 'Prompt gerado.',
        ]);
    }

    public function test_controllers_nao_capturam_exception_generica_na_exclusao(): void
    {
        $language = (string) file_get_contents(app_path('Http/Controllers/LanguageController.php'));
        $architecture = (string) file_get_contents(app_path('Http/Controllers/ArchitectureController.php'));

        $this->assertMatchesRegularExpression('/function destroy\(\$id\)\s*\{[\s\S]*catch \(QueryException/m', $language);
        $this->assertMatchesRegularExpression('/function destroy\(\$id\)\s*\{[\s\S]*catch \(QueryException/m', $architecture);
        $this->assertDoesNotMatchRegularExpression('/function destroy\(\$id\)\s*\{[\s\S]*catch \(Exception/m', $language);
        $this->assertDoesNotMatchRegularExpression('/function destroy\(\$id\)\s*\{[\s\S]*catch \(Exception/m', $architecture);
    }
}
