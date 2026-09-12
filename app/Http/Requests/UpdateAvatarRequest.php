<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avatar.required' => 'Selecione uma foto para continuar.',
            'avatar.image' => 'O arquivo precisa ser uma imagem.',
            'avatar.mimes' => 'Use JPG, PNG ou WEBP.',
            'avatar.max' => 'A foto pode ter no máximo 2 MB.',
        ];
    }
}
