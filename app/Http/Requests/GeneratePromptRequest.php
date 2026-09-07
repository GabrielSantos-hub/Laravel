<?php

namespace App\Http\Requests;

use App\Models\Template;
use App\Services\AI\IntentAnalyzer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GeneratePromptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * O catálogo (arquitetura, linguagem, framework) passou a ser opcional: o
     * IntentAnalyzer deduz essas informações do próprio texto. Os campos
     * continuam sendo aceitos e validados porque a tela ainda os envia, e
     * quando presentes são gravados no histórico.
     */
    public function rules(): array
    {
        return [
            'user_input' => [
                'required',
                'string',
                'min:'.IntentAnalyzer::MIN_INPUT_LENGTH,
                'max:'.IntentAnalyzer::MAX_INPUT_LENGTH,
            ],
            'template_id' => ['nullable', 'integer', 'exists:templates,id'],
            'architecture_id' => ['nullable', 'integer', 'exists:architectures,id'],
            'language_id' => ['nullable', 'integer', 'exists:languages,id'],
            'framework_id' => ['nullable', 'integer', 'exists:frameworks,id'],
        ];
    }

    /**
     * Compatibilidade: o formulário de geração ainda envia o campo com o nome
     * antigo (input_text).
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('user_input') && $this->filled('input_text')) {
            $this->merge(['user_input' => $this->input('input_text')]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('template_id')) {
                return;
            }

            $ativo = Template::query()
                ->whereKey($this->input('template_id'))
                ->where('is_active', true)
                ->exists();

            if (! $ativo) {
                $validator->errors()->add(
                    'template_id',
                    'O template selecionado está inativo ou não existe.'
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
            'user_input.required' => 'Descreva o que você precisa gerar.',
            'user_input.min' => 'Descreva sua intenção com mais detalhes: são necessários ao menos :min caracteres.',
            'user_input.max' => 'Sua descrição é longa demais: o limite é de :max caracteres.',
            'template_id.exists' => 'O template selecionado não existe.',
            'architecture_id.exists' => 'A arquitetura selecionada não existe.',
            'language_id.exists' => 'A linguagem selecionada não existe.',
            'framework_id.exists' => 'O framework selecionado não existe.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_input' => 'descrição da intenção',
            'template_id' => 'template',
            'architecture_id' => 'arquitetura',
            'language_id' => 'linguagem',
            'framework_id' => 'framework',
        ];
    }
}
