<?php

namespace App\Services\AI;

use App\Contracts\AIProviderInterface;
use App\Models\Template;
use App\Services\PromptOutputPolicy;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Terceira etapa do pipeline: funde a intenção estruturada com o template
 * escolhido e devolve o prompt final.
 *
 * O caminho principal envia a intenção e o template ao provedor de IA para
 * uma reescrita fluida — não uma substituição mecânica de chaves. Se o
 * provedor falhar, devolver texto vazio ou quebrar de qualquer outra forma,
 * a composição cai para a interpolação determinística do TemplateInterpolator:
 * o usuário sempre recebe um prompt utilizável, nunca um erro.
 */
class PromptComposer
{
    public const INSTRUCTION = <<<'TXT'
        Você é um Engenheiro de Prompt especialista. Receba a intenção bruta do usuário: '{intencao}'. Normalize erros de digitação, remova qualquer ruído e reescreva essa ideia transformando-a em uma especificação de software fluida, elegante e contínua.

        NÃO faça 'copia e cola' do texto do usuário. Em vez de criar um bloco estático como 'Solicitação do usuário: [texto bruto]', integre a ideia de forma orgânica ao corpo do prompt final, descrevendo a arquitetura, o fluxo de dados e os requisitos como um texto técnico profissional coeso.

        Regras de composição:
        - Use o template apenas como guia estrutural; não faça substituição mecânica de chaves.
        - Resolva os blocos condicionais {% if chave %}...{% endif %}, mantendo o conteúdo apenas quando a variável tiver valor e removendo o bloco inteiro caso contrário.
        - {user_input} traz o pedido específico e o briefing: preserve todos os detalhes concretos (serviços, endpoints, parâmetros, tecnologias). O template só estrutura; nunca apague nem generalize esses detalhes.
        - Produza um documento coeso e bem redigido, sem partes que pareçam inserções brutas de formulário.
        - Não invente requisitos, tecnologias ou restrições que não estejam na intenção ou no briefing.
        - Se o pedido proibir código ou pedir só documentação, não solicite implementação em código.
        - Responda apenas com o prompt final, sem comentários, explicações ou cercas de código.
        TXT;

    private readonly IntentSynthesizer $synthesizer;

    public function __construct(
        private readonly AIProviderInterface $provider,
        private readonly TemplateInterpolator $interpolator = new TemplateInterpolator,
        private readonly ?LoggerInterface $logger = null,
        ?IntentSynthesizer $synthesizer = null,
        private readonly PromptOutputPolicy $policy = new PromptOutputPolicy,
    ) {
        $this->synthesizer = $synthesizer ?? new IntentSynthesizer($this->provider);
    }

    /**
     * @param  array<string, mixed>  $structuredIntent  Saída do IntentAnalyzer.
     * @param  array<string, mixed>  $customVariables  Marcadores dinâmicos do
     *                                                 template, preenchidos na tela.
     * @param  string|null  $rawIntent  Texto original digitado pelo usuário.
     */
    public function compose(array $structuredIntent, Template $template, array $customVariables = [], ?string $rawIntent = null): string
    {
        $body = (string) $template->corpo_template;
        $variables = $this->variables($structuredIntent, $customVariables, $rawIntent);
        $pedido = $variables['intencao'] ?? '';

        $proseOnly = $this->policy->isProseOnly($pedido, $structuredIntent);

        if ($proseOnly) {
            $body = $this->policy->stripCodeInstructions($body);
            $body = $this->policy->rewriteProseFraming($body);
        }

        try {
            $composed = $this->cleanUp(
                $this->provider->composePrompt(self::INSTRUCTION, $body, $variables)
            );

            if ($composed === '') {
                throw new RuntimeException('O provedor devolveu uma composição vazia.');
            }

            // Rede de segurança: um LLM pode deixar placeholders para trás.
            $composed = $this->interpolator->resolvePlaceholders($composed, $variables);

            return $proseOnly
                ? $this->policy->rewriteProseFraming($composed)
                : $composed;
        } catch (Throwable $e) {
            $this->logger?->error($e->getMessage(), [
                'template_id' => $template->getKey(),
                'exception' => $e->getMessage(),
            ]);

            $fallback = $this->interpolator->render($body, $variables);

            return $proseOnly
                ? $this->policy->rewriteProseFraming($fallback)
                : $fallback;
        }
    }

    /**
     * Achata a intenção estruturada no mapa de variáveis que o template usa.
     *
     * As chaves derivadas da intenção são as de TemplateInterpolator::
     * RESERVED_VARIABLES e têm precedência sobre as dinâmicas: um template não
     * consegue redefinir {user_input} através de um campo da tela.
     *
     * @param  array<string, mixed>  $intent
     * @param  array<string, mixed>  $customVariables
     * @return array<string, string>
     */
    private function variables(array $intent, array $customVariables = [], ?string $rawIntent = null): array
    {
        $technologies = $this->stringList($intent['technologies'] ?? []);
        $constraints = $this->stringList($intent['constraints'] ?? []);
        $objective = $this->text($intent['objective'] ?? null);
        $intencao = trim((string) $rawIntent) !== '' ? trim((string) $rawIntent) : $objective;
        $briefing = $this->synthesizer->frame($intencao, $intent);

        return [
            'intencao' => $intencao,
            'user_input' => $briefing,
            'objective' => $objective,
            'type' => $this->text($intent['type'] ?? null),
            'architecture' => $this->text($intent['architecture'] ?? null),
            'technologies' => implode(', ', $technologies),
            // A intenção traz uma lista plana de tecnologias, sem separar o que
            // é linguagem do que é framework. Para manter compatibilidade com
            // os templates atuais, o primeiro item alimenta {language} e o
            // segundo {framework}; templates que precisam de precisão devem
            // usar {technologies}.
            'language' => $technologies[0] ?? '',
            'framework' => $technologies[1] ?? '',
            'constraints' => implode("\n", array_map(
                static fn (string $constraint): string => "- {$constraint}",
                $constraints
            )),
        ] + $this->stringMap($customVariables);
    }

    /**
     * @param  array<mixed, mixed>  $values
     * @return array<string, string>
     */
    private function stringMap(array $values): array
    {
        $map = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && $key !== '') {
                $map[$key] = $this->text($value);
            }
        }

        return $map;
    }

    /**
     * Alguns modelos embrulham a resposta em cerca de código mesmo quando
     * instruídos a não fazê-lo.
     */
    private function cleanUp(string $output): string
    {
        $output = trim($output);

        if (preg_match('/^```[a-z]*\s*\n(.*)\n```$/su', $output, $matches) === 1) {
            return trim($matches[1]);
        }

        return $output;
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            $item = $this->text($item);

            if ($item !== '') {
                $items[] = $item;
            }
        }

        return array_values(array_unique($items));
    }

    private function text(mixed $value): string
    {
        if (is_bool($value) || ! is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }
}
