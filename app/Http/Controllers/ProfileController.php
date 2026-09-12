<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAvatarRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit', [
            'user' => auth()->user(),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->safe()->only(['name']);

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $this->storeAvatar($user, $request->file('avatar'));
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
        $user->update([
            'avatar' => $this->storeAvatar($user, $request->file('avatar')),
        ]);

        return response()->json([
            'avatar_url' => $user->avatarUrl(),
            'message' => 'Foto de perfil atualizada.',
        ]);
    }

    private function storeAvatar(User $user, UploadedFile $arquivo): string
    {
        if (filled($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $extensao = $arquivo->guessExtension() ?: 'png';

        return $arquivo->storeAs(
            'avatars',
            $user->id.'-'.Str::uuid().'.'.$extensao,
            'public'
        );
    }
}
