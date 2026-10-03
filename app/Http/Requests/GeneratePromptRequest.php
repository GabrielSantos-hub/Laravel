<?php

namespace App\Http\Requests;

use App\Exceptions\InputUnprocessableException;
use App\Services\AI\IntentAnalyzer;
use App\Services\Guardrails\InputSanityGuardrail;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Throwable;

class GeneratePromptRequest extends FormRequest
{
    public const INVALID_UTF8_MESSAGE = 'O texto contém caracteres inválidos.';

    public const VARIABLE_KEY_MESSAGE = 'O nome da variável é inválido.';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Só presença, tipo e tamanho. A coerência semântica é decidida pela IA
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
            'nao_salvar_historico' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $intencao = $this->input('intencao');

        if (! $this->filledString($intencao)) {
            $intencao = $this->input('user_input') ?? $this->input('input_text');
        }

        if (is_string($intencao)) {
            $encodingOk = mb_check_encoding($intencao, 'UTF-8');
            $intencao = $encodingOk ? $this->sanitizeUserText($intencao) : '';
            $this->merge([
                'intencao' => $intencao,
                'user_input' => $intencao,
                'intencao_encoding' => $encodingOk ? 'ok' : 'invalid',
            ]);
        }

        $ids = [];
        foreach (['architecture_id', 'language_id', 'framework_id'] as $campo) {
            $valor = $this->input($campo);
            if ($valor === '' || $valor === false) {
                $ids[$campo] = null;
            }
        }
        if ($ids !== []) {
            $this->merge($ids);
        }

        $variaveis = $this->input('variables');
        if (is_array($variaveis)) {
            $limpas = [];
            foreach ($variaveis as $chave => $valor) {
                $limpas[$chave] = is_string($valor) ? $this->sanitizeUserText($valor) : $valor;
            }
            $this->merge(['variables' => $limpas]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('intencao_encoding') === 'invalid') {
                $validator->errors()->forget('intencao');
                $validator->errors()->add('intencao', self::INVALID_UTF8_MESSAGE);

                return;
            }

            $intencao = $this->input('intencao');

            if ($validator->errors()->has('intencao')) {
                return;
            }

            if (! is_string($intencao)) {
                return;
            }

            $guardrail = app(InputSanityGuardrail::class);

            try {
                $guardrail->assertSane($intencao);
            } catch (InputUnprocessableException $e) {
                $logger = app(\App\Services\Security\SecurityLogger::class);
                if ($guardrail->isPromptInjection($intencao)) {
                    $logger->log('prompt_injection_detected', [
                        'category' => $guardrail->injectionCategory($intencao),
                        'length' => mb_strlen($intencao),
                    ]);
                } else {
                    $logger->log('guardrail_rejected', [
                        'category' => $guardrail->rejectionCategory($intencao),
                        'length' => mb_strlen($intencao),
                    ]);
                }
                $validator->errors()->add('intencao', $e->getMessage());

                return;
            } catch (Throwable) {
                $validator->errors()->add('intencao', 'Não foi possível processar a solicitação.');

                return;
            }

            $variaveis = $this->input('variables');

            if (! is_array($variaveis)) {
                return;
            }

            $logger = app(\App\Services\Security\SecurityLogger::class);

            foreach ($variaveis as $chave => $valor) {
                if (! is_string($chave) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $chave) !== 1) {
                    $validator->errors()->add('variables.'.$chave, self::VARIABLE_KEY_MESSAGE);

                    continue;
                }

                if (! is_string($valor)) {
                    continue;
                }

                if (! mb_check_encoding($valor, 'UTF-8')) {
                    $validator->errors()->add('variables.'.$chave, self::INVALID_UTF8_MESSAGE);

                    continue;
                }

                if ($guardrail->isPromptInjection($valor)) {
                    $logger->log('prompt_injection_detected', [
                        'category' => $guardrail->injectionCategory($valor),
                        'length' => mb_strlen($valor),
                    ]);
                    $validator->errors()->add(
                        'variables.'.$chave,
                        InputUnprocessableException::MESSAGE
                    );
                } elseif ($guardrail->isMalicious($valor)) {
                    $logger->log('guardrail_rejected', [
                        'category' => 'xss_sqli',
                        'length' => mb_strlen($valor),
                    ]);
                    $validator->errors()->add(
                        'variables.'.$chave,
                        InputUnprocessableException::MESSAGE
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'intencao.required' => 'Descreva o que você precisa gerar.',
            'intencao.string' => 'A descrição da intenção precisa ser um texto.',
            'intencao.min' => 'Descreva sua intenção com mais detalhes: são necessários ao menos :min caracteres.',
            'intencao.max' => 'Sua descrição é longa demais: o limite é de :max caracteres.',
            'architecture_id.integer' => 'A arquitetura selecionada é inválida.',
            'architecture_id.exists' => 'A arquitetura selecionada não existe.',
            'language_id.integer' => 'A linguagem selecionada é inválida.',
            'language_id.exists' => 'A linguagem selecionada não existe.',
            'framework_id.integer' => 'O framework selecionado é inválido.',
            'framework_id.exists' => 'O framework selecionado não existe.',
            'variables.array' => 'As variáveis precisam ser enviadas como uma lista.',
            'variables.*.string' => 'O valor de cada variável precisa ser um texto.',
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

    private function filledString(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private function sanitizeUserText(string $text): string
    {
        $text = str_replace("\0", '', $text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        if (! mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }

        return trim($text);
    }
}
