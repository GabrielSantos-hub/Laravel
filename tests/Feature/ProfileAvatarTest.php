<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_nao_acessa_o_perfil(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    }

    public function test_usuario_atualiza_nome_e_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['name' => 'Nome Antigo']);
        $arquivo = $this->avatarPng();

        $resposta = $this->actingAs($user)->put(route('profile.update'), [
            'name' => 'Nome Novo',
            'avatar' => $arquivo,
        ]);

        $resposta->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertSame('Nome Novo', $user->name);
        $this->assertNotNull($user->avatar);
        Storage::disk('public')->assertExists($user->avatar);

        $home = $this->actingAs($user)->get(route('home'));
        $home->assertOk();
        $home->assertSee('Meu perfil', false);
        $home->assertSee($user->avatarUrl(), false);
        $home->assertDontSee('user-avatar-fallback', false);
    }

    public function test_header_usa_icone_padrao_sem_avatar(): void
    {
        $user = User::factory()->create();

        $resposta = $this->actingAs($user)->get(route('home'));

        $resposta->assertOk();
        $resposta->assertSee('user-avatar-fallback', false);
        $resposta->assertSee('Meu perfil', false);
    }

    private function avatarPng(): UploadedFile
    {
        $caminho = sys_get_temp_dir().DIRECTORY_SEPARATOR.'gueass-avatar-'.uniqid().'.png';
        $png = hex2bin('89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082');
        file_put_contents($caminho, $png);

        return new UploadedFile($caminho, 'foto.png', 'image/png', null, true);
    }
}
