<?php

namespace App\Http\Controllers;

use App\Exceptions\CannotRemoveLastAdminException;
use App\Models\User;
use App\Services\Security\AdminAuditor;
use App\Support\PasswordRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly AdminAuditor $auditor,
    ) {}

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

        $this->auditor->record('admin_password_reset', $user, [
            'target_user_id' => $user->id,
        ]);

        return back()
            ->with('status', "Senha temporária gerada para {$user->name}. Ela será exibida uma única vez.")
            ->with('temporary_password', $temporary);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->isAdmin()) {
            return back()->withErrors([
                'delete' => $request->user()->is($user)
                    ? 'Você não pode excluir a própria conta nesta tela.'
                    : 'Não é possível excluir outra conta de administrador.',
            ]);
        }

        $targetId = $user->id;
        $targetName = $user->name;
        $avatar = $user->avatar;

        try {
            $user->delete();
        } catch (CannotRemoveLastAdminException $e) {
            return back()->withErrors(['delete' => $e->getMessage()]);
        }

        if (filled($avatar)) {
            Storage::disk('public')->delete($avatar);
        }

        $this->auditor->record('admin_user_deleted', null, [
            'target_user_id' => $targetId,
            'target_name' => $targetName,
        ]);

        return back()->with('status', "Usuário {$targetName} excluído.");
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
