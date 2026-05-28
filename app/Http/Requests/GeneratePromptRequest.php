<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GeneratePromptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'template_id' => ['required', 'integer', 'exists:templates,id'],
            'architecture_id' => ['required', 'integer', 'exists:architectures,id'],
            'language_id' => ['required', 'integer', 'exists:languages,id'],
            'framework_id' => ['nullable', 'integer', 'exists:frameworks,id'],
            'input_text' => ['required', 'string', 'max:20000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $templateId = $this->input('template_id');
            $exists = \App\Models\Template::query()
                ->whereKey($this->input('template_id'))
                ->where('is_active', true)
                ->exists();

            if (! $exists) {
                $validator->errors()->add(
                    'template_id',
                    'O template selecionado está inativo ou não existe.'
                );
            }
        });
    }
}
