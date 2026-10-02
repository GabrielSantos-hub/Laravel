<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Security\AdminAuditor;
use App\Support\PasswordRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(): View
    {
        $users = User::query()->orderBy('name')->get();

        return view('admin.users.index', compact('users'));
    }

    public function resetPassword(User $user): RedirectResponse
    {
        $temporary = $this->temporaryPassword();

        $user->forceFill([
            'password' => $temporary,
            'must_change_password' => true,
        ])->save();

        // auditoria: admin_password_reset
        app(AdminAuditor::class)->record('admin_password_reset', $user, [
            'target_user_id' => $user->id,
        ]);

        return back()
            ->with('status', "Senha temporária gerada para {$user->name}. Ela será exibida uma única vez.")
            ->with('temporary_password', $temporary);
    }

    private function temporaryPassword(): string
    {
        do {
            $candidate = Str::password(16);
        } while (Validator::make(
            ['password' => $candidate, 'password_confirmation' => $candidate],
            ['password' => PasswordRules::required()]
        )->fails());

        return $candidate;
    }
}
