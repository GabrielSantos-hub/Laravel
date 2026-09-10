<?php

namespace App\Http\Requests;

use App\Services\AI\IntentAnalyzer;
use Illuminate\Foundation\Http\FormRequest;

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
            'architecture_id' => ['nullable', 'integer', 'exists:architectures,id'],
            'language_id' => ['nullable', 'integer', 'exists:languages,id'],
            'framework_id' => ['nullable', 'integer', 'exists:frameworks,id'],
            'template_id' => ['nullable', 'integer', 'exists:templates,id'],
            // Marcadores dinâmicos do template escolhido, no formato
            // variables[NOME_DA_VARIAVEL]. Quais chaves existem depende do
            // corpo do template, então aqui só validamos o formato.
            'variables' => ['nullable', 'array'],
            'variables.*' => ['nullable', 'string', 'max:2000'],
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_input.required' => 'Descreva o que você precisa gerar.',
            'user_input.min' => 'Descreva sua intenção com mais detalhes: são necessários ao menos :min caracteres.',
            'user_input.max' => 'Sua descrição é longa demais: o limite é de :max caracteres.',
            'architecture_id.exists' => 'A arquitetura selecionada não existe.',
            'language_id.exists' => 'A linguagem selecionada não existe.',
            'framework_id.exists' => 'O framework selecionado não existe.',
            'template_id.exists' => 'O template selecionado não existe.',
            'variables.*.max' => 'O valor informado para uma das variáveis é longo demais: o limite é de :max caracteres.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_input' => 'descrição da intenção',
            'architecture_id' => 'arquitetura',
            'language_id' => 'linguagem',
            'framework_id' => 'framework',
            'template_id' => 'template',
        ];
    }
}
