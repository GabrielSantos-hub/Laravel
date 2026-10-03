<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class DeleteAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();

            if ($user instanceof User && $user->isLastAdmin()) {
                $validator->errors()->add(
                    'current_password',
                    'Não é possível excluir a conta: você é o único administrador.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Confirme a senha para excluir a conta.',
            'current_password.current_password' => 'A senha informada não confere.',
        ];
    }
}
