<?php

namespace App\Http\Controllers;

use App\Exceptions\AvatarRejectedException;
use App\Http\Requests\DeleteAccountRequest;
use App\Http\Requests\UpdateAvatarRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use App\Services\AvatarSanitizer;
use App\Services\Security\SecurityLogger;
use App\Support\PasswordRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit', [
            'user' => auth()->user(),
        ]);
    }

    public function editForcedPassword(): View
    {
        return view('profile.forced-password');
    }

    public function updateForcedPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => PasswordRules::required(),
        ]);

        $request->user()->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
        ])->save();

        // auditoria: forced_password_change
        app(\App\Services\Security\SecurityLogger::class)->log('forced_password_change');

        return redirect()->route('home')->with('sucesso', 'Senha atualizada. Você já pode usar o sistema.');
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->safe()->only(['name']);

        if (filled($request->input('password'))) {
            $data['password'] = $request->input('password');
            app(\App\Services\Security\SecurityLogger::class)->log('password_changed');
        }

        if ($request->hasFile('avatar')) {
            try {
                $data['avatar'] = $this->storeAvatar($user, $request->file('avatar'));
            } catch (AvatarRejectedException $e) {
                return back()->withErrors(['avatar' => $e->getMessage()]);
            }
        }

        $user->update($data);

        return redirect()->route('profile.edit')->with('sucesso', 'Perfil atualizado.');
    }

    /**
     * Recebe o recorte 1:1 enviado pelo Cropper.js e devolve a URL nova
     * para a tela atualizar o <img> sem recarregar.
     */
    public function updateAvatar(UpdateAvatarRequest $request): JsonResponse
    {
        $user = $request->user();

        try {
            $user->update([
                'avatar' => $this->storeAvatar($user, $request->file('avatar')),
            ]);
        } catch (AvatarRejectedException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'avatar_url' => $user->avatarUrl(),
            'message' => 'Foto de perfil atualizada.',
        ]);
    }

    private function storeAvatar(User $user, UploadedFile $arquivo): string
    {
        try {
            $png = app(AvatarSanitizer::class)->sanitize($arquivo);
        } catch (AvatarRejectedException $e) {
            app(SecurityLogger::class)->log('avatar_rejected', [
                'reason' => 'content',
            ]);

            throw $e;
        }

        if (filled($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = 'avatars/'.$user->id.'-'.Str::uuid().'.png';
        Storage::disk('public')->put($path, $png);

        return $path;
    }

    public function exportHistory(): StreamedResponse
    {
        $user = request()->user();
        $prompts = $user->prompts()
            ->orderBy('id')
            ->get(['id', 'input_text', 'output_text', 'is_useful', 'created_at', 'updated_at']);

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'prompts' => $prompts,
        ];

        $filename = 'gueass-historico-'.$user->id.'.json';

        app(\App\Services\Security\SecurityLogger::class)->log('data_exported', [
            'prompt_count' => $prompts->count(),
        ]);

        return response()->streamDownload(function () use ($payload): void {
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }, $filename, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    public function destroy(DeleteAccountRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (filled($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $userId = $user->id;
        $email = $user->email;

        $user->prompts()->delete();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $user->delete();

        app(\App\Services\Security\SecurityLogger::class)->log('account_deleted', [
            'user_id' => $userId,
            'email' => $email,
        ]);

        return redirect()
            ->route('login')
            ->with('status', 'Sua conta e todos os seus dados foram excluídos.');
    }
}
