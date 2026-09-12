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
     * Só presença e tamanho. A coerência semântica é decidida pela IA
     * na saída JSON estruturada (`valido` / `motivo_rejeicao`).
     */
    public function rules(): array
    {
        return [
            'intencao' => [
                'required',
                'string',
                'min:'.IntentAnalyzer::MIN_INPUT_LENGTH,
                'max:'.IntentAnalyzer::MAX_INPUT_LENGTH,
            ],
            'architecture_id' => ['nullable', 'integer', 'exists:architectures,id'],
            'language_id' => ['nullable', 'integer', 'exists:languages,id'],
            'framework_id' => ['nullable', 'integer', 'exists:frameworks,id'],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $intencao = $this->input('intencao');

        if (! filled($intencao)) {
            $intencao = $this->input('user_input') ?? $this->input('input_text');
        }

        if (is_string($intencao)) {
            $this->merge([
                'intencao' => $intencao,
                'user_input' => $intencao,
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'intencao.required' => 'Descreva o que você precisa gerar.',
            'intencao.min' => 'Descreva sua intenção com mais detalhes: são necessários ao menos :min caracteres.',
            'intencao.max' => 'Sua descrição é longa demais: o limite é de :max caracteres.',
            'architecture_id.exists' => 'A arquitetura selecionada não existe.',
            'language_id.exists' => 'A linguagem selecionada não existe.',
            'framework_id.exists' => 'O framework selecionado não existe.',
            'variables.*.max' => 'O valor informado para uma das variáveis é longo demais: o limite é de :max caracteres.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'intencao' => 'descrição da intenção',
            'architecture_id' => 'arquitetura',
            'language_id' => 'linguagem',
            'framework_id' => 'framework',
        ];
    }
}
