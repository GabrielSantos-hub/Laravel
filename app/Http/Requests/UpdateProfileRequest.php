<?php

namespace App\Http\Requests;

use App\Support\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'current_password' => ['required_with:password', 'current_password'],
            'password' => PasswordRules::optional(),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome.',
            'name.string' => 'O nome precisa ser um texto.',
            'name.max' => 'O nome pode ter no máximo :max caracteres.',
        ];
    }
}
