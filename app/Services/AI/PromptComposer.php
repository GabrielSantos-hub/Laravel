<?php

namespace App\Services\AI;

use App\Contracts\AIProviderInterface;
use App\Models\Template;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Terceira etapa do pipeline: funde a intenção estruturada com o template
 * escolhido e devolve o prompt final.
 *
 * O caminho principal delega ao provedor de IA, que além de substituir as
 * variáveis refina a redação. Se o provedor falhar, devolver texto vazio ou
 * quebrar de qualquer outra forma, a composição cai para a interpolação
 * determinística do TemplateInterpolator: o usuário sempre recebe um prompt
 * utilizável, nunca um erro.
 */
class PromptComposer
{
    private const INSTRUCTION = <<<'TXT'
        Você é um engenheiro de prompts. Analise a intenção do usuário: '{intencao}'. Reescreva e expanda essa ideia em termos técnicos claros, identificando a regra de negócio principal, 2 a 3 requisitos implícitos e o fluxo do usuário. Integre esse conteúdo de forma natural e fluida na estrutura do template final.

        Regras:
        - Substitua todo placeholder no formato {chave} pelo valor correspondente das variáveis.
        - Resolva os blocos condicionais {% if chave %}...{% endif %}, mantendo o conteúdo apenas quando a variável tiver valor e removendo o bloco inteiro caso contrário.
        - {user_input} já é um briefing técnico expandido: integre-o em prosa, sem aspas e sem colar o texto cru do usuário.
        - Preserve a estrutura, as seções e o tom do template.
        - Refine a redação para ficar clara, direta e sem redundância.
        - Não invente requisitos, tecnologias ou restrições que não estejam na intenção ou no briefing.
        - Responda apenas com o prompt final, sem comentários, explicações ou cercas de código.
        TXT;

    private readonly IntentSynthesizer $synthesizer;

    public function __construct(
        private readonly AIProviderInterface $provider,
        private readonly TemplateInterpolator $interpolator = new TemplateInterpolator,
        private readonly ?LoggerInterface $logger = null,
        ?IntentSynthesizer $synthesizer = null,
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

        try {
            $composed = $this->cleanUp(
                $this->provider->composePrompt(self::INSTRUCTION, $body, $variables)
            );

            if ($composed === '') {
                throw new RuntimeException('O provedor devolveu uma composição vazia.');
            }

            // Rede de segurança: um LLM pode deixar placeholders para trás.
            return $this->interpolator->resolvePlaceholders($composed, $variables);
        } catch (Throwable $e) {
            $this->logger?->warning('Composição via IA falhou; usando interpolação simples.', [
                'template_id' => $template->getKey(),
                'exception' => $e->getMessage(),
            ]);

            return $this->interpolator->render($body, $variables);
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
        $briefing = $this->synthesizer->synthesize($intencao, $intent);

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
