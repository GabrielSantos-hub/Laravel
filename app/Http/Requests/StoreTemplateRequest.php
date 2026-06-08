<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => 'required|string|max:255|unique:templates,nome',
            'corpo_template' => ['required', 'string', 'max:500000'],
            'versao' => ['nullable', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
