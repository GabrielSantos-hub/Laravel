<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetUserPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(): View
    {
        $users = User::query()->orderBy('name')->get();

        return view('admin.users.index', compact('users'));
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): RedirectResponse
    {
        $user->forceFill([
            'password' => $request->validated('password'),
        ])->save();

        return back()->with('status', "Senha redefinida para {$user->name}.");
    }
}
