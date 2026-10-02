<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\PasswordRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateAdminCommand extends Command
{
    protected $signature = 'gueass:create-admin
                            {--email= : E-mail do administrador}
                            {--password= : Senha (também pode vir de ADMIN_PASSWORD)}';

    protected $description = 'Cria o administrador inicial do GUEASS';

    public function handle(): int
    {
        $email = $this->option('email') ?: env('ADMIN_EMAIL') ?: $this->ask('E-mail do administrador');
        $password = $this->option('password') ?: env('ADMIN_PASSWORD');

        if (! filled($password)) {
            $password = $this->secret('Senha do administrador');
            $confirmation = $this->secret('Confirme a senha');

            if ($password !== $confirmation) {
                $this->error('A confirmação da senha não confere.');

                return self::FAILURE;
            }
        }

        try {
            Validator::make(
                [
                    'email' => $email,
                    'password' => $password,
                    'password_confirmation' => $password,
                ],
                [
                    'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                    'password' => PasswordRules::required(),
                ]
            )->validate();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => 'Administrador',
            'email' => $email,
            'password' => $password,
        ]);
        $user->forceFill(['role' => 'ADM'])->save();

        $this->info('Administrador criado: '.$user->email);

        return self::SUCCESS;
    }
}
