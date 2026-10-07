<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditClearTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_ve_o_botao_de_limpar_auditoria(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.audit.index'))
            ->assertOk()
            ->assertSee('aria-label="Limpar auditoria"', false)
            ->assertSee('fa-arrows-rotate', false)
            ->assertSee('id="clear-audit-modal"', false)
            ->assertSee('Deseja realmente esvaziar os registros de auditoria?', false);
    }

    public function test_admin_limpa_a_auditoria_e_registra_o_evento(): void
    {
        $admin = $this->admin();

        AuditLog::query()->create([
            'user_id' => $admin->id,
            'action' => 'admin_password_reset',
            'created_at' => now(),
        ]);
        AuditLog::query()->create([
            'action' => 'login_success',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.audit.clear'))
            ->assertRedirect(route('admin.audit.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin_audit_cleared',
            'user_id' => $admin->id,
        ]);
    }

    public function test_usuario_comum_nao_limpa_a_auditoria(): void
    {
        AuditLog::query()->create([
            'action' => 'login_success',
            'created_at' => now(),
        ]);

        $this->actingAs(User::factory()->create(['role' => 'USU']))
            ->delete(route('admin.audit.clear'))
            ->assertForbidden();

        $this->assertDatabaseHas('audit_logs', ['action' => 'login_success']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'admin_audit_cleared']);
    }

    public function test_visitante_nao_limpa_a_auditoria(): void
    {
        AuditLog::query()->create([
            'action' => 'login_success',
            'created_at' => now(),
        ]);

        $this->delete(route('admin.audit.clear'))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('audit_logs', ['action' => 'login_success']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'ADM']);
    }
}
