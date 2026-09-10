<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidIntentException;
use App\Exceptions\NoCompatibleTemplateException;
use App\Http\Requests\GeneratePromptRequest;
use App\Models\Architecture;
use App\Models\Framework;
use App\Models\Language;
use App\Models\Prompt;
use App\Models\Template;
use App\Services\PromptPipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PromptController extends Controller
{
    public function __construct(
        protected PromptPipelineService $pipeline
    ) {}

    public function index(): View
    {
        // A escolha manual vem, nesta ordem, de um envio que voltou com erro,
        // do botão "Selecionar" do catálogo (?template_id=) e do que o usuário
        // escolheu na última geração. Nula significa seleção automática.
        $selectedTemplate = $this->template(
            old('template_id', request('template_id', session('last_template_id')))
        );

        return view('prompts.index', [
            'architectures' => Architecture::query()->orderBy('nome')->get(),
            'languages' => Language::query()->orderBy('nome')->get(),
            'frameworks' => Framework::query()->with('language')->orderBy('nome')->get(),
            'templates' => Template::query()->where('is_active', true)->orderBy('nome')->get(),
            'selectedTemplate' => $selectedTemplate,
            'templateVariables' => $selectedTemplate?->dynamicVariables() ?? [],
            'variableValues' => session('last_variables', []),
            // O template de fato usado na última geração, que no modo
            // automático é escolhido pelo pipeline e não pelo usuário.
            'activeTemplate' => $this->template(session('selected_template_id')),
            'lastPromptId' => session('last_prompt_id'),
        ]);
    }

    public function show(Prompt $prompt): View
    {
        $this->autorizarDono($prompt, 'visualizar');

        $prompt->load(['template', 'architecture', 'language', 'framework']);

        return view('prompts.show', compact('prompt'));
    }

    /**
     * Roda o pipeline completo e grava o resultado no histórico.
     *
     * As falhas de domínio viram ValidationException, que o Laravel já converte
     * no formato certo dos dois lados: redirect com erros para o formulário e
     * 422 com o corpo de erros para clientes que esperam JSON.
     */
    public function generate(GeneratePromptRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $variaveis = $this->variaveisDinamicas($validated['variables'] ?? []);

        try {
            $resultado = $this->pipeline->generate(
                $validated['user_input'],
                forcedTemplateId: isset($validated['template_id']) ? (int) $validated['template_id'] : null,
                catalogHints: [
                    'language_id' => $validated['language_id'] ?? null,
                    'framework_id' => $validated['framework_id'] ?? null,
                    'architecture_id' => $validated['architecture_id'] ?? null,
                ],
                customVariables: $variaveis,
            );
        } catch (InvalidIntentException $e) {
            throw ValidationException::withMessages(['user_input' => $e->getMessage()]);
        } catch (NoCompatibleTemplateException $e) {
            throw ValidationException::withMessages(['user_input' => $e->getMessage()]);
        }

        $prompt = Prompt::query()->create([
            'user_id' => Auth::id(),
            'template_id' => $resultado->template->getKey(),
            'architecture_id' => $validated['architecture_id'] ?? null,
            'language_id' => $validated['language_id'] ?? null,
            'framework_id' => $validated['framework_id'] ?? null,
            'input_text' => $validated['user_input'],
            'output_text' => $resultado->prompt,
        ]);

        if ($request->expectsJson()) {
            return response()->json(
                ['prompt_id' => $prompt->id] + $resultado->toArray(),
                201
            );
        }

        return redirect()
            ->route('home')
            ->with('sucesso', 'Prompt gerado e salvo no histórico.')
            ->with('last_output', $resultado->prompt)
            ->with('last_prompt_id', $prompt->id)
            ->with('last_variables', $variaveis)
            ->with('last_template_id', $validated['template_id'] ?? null)
            ->with('selected_template_id', $resultado->template->getKey());
    }

    private function template(mixed $id): ?Template
    {
        return is_numeric($id) ? Template::query()->find((int) $id) : null;
    }

    /**
     * Registra o voto de utilidade do prompt. Responde em JSON porque a tela
     * envia por fetch, sem recarregar.
     */
    public function feedback(Request $request, Prompt $prompt): JsonResponse
    {
        $this->autorizarDono($prompt, 'avaliar');

        $validated = $request->validate(
            ['is_useful' => ['required', 'boolean']],
            ['is_useful.required' => 'Informe se o prompt foi útil.']
        );

        $prompt->update(['is_useful' => (bool) $validated['is_useful']]);

        return response()->json([
            'prompt_id' => $prompt->id,
            'is_useful' => $prompt->is_useful,
        ]);
    }

    /**
     * Sanitiza os marcadores dinâmicos vindos do formulário: só passam chaves
     * com formato de identificador (as mesmas que o extrator reconhece) e
     * valores preenchidos — variável em branco deixa o placeholder ser tratado
     * como ausente, o que também apaga os blocos {% if %} que dependem dela.
     *
     * @param  array<mixed, mixed>  $variables
     * @return array<string, string>
     */
    private function variaveisDinamicas(array $variables): array
    {
        $limpas = [];

        foreach ($variables as $nome => $valor) {
            if (! is_string($nome) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $nome) !== 1) {
                continue;
            }

            $valor = is_scalar($valor) ? trim((string) $valor) : '';

            if ($valor !== '') {
                $limpas[$nome] = $valor;
            }
        }

        return $limpas;
    }

    public function destroy($id): RedirectResponse
    {
        $prompt = Prompt::findOrFail($id);

        $this->autorizarDono($prompt, 'excluir');

        $prompt->delete();

        return redirect()->route('home')->with('sucesso', 'Prompt removido do histórico com sucesso!');
    }

    /**
     * O histórico é pessoal e o id do prompt vem na URL, então sem esta
     * verificação qualquer usuário autenticado leria ou apagaria o prompt de
     * outro só trocando o número.
     *
     * O cast protege drivers que devolvem a chave estrangeira como string e
     * cobre `user_id` nulo (prompt órfão), que nunca casa com um id de sessão.
     */
    private function autorizarDono(Prompt $prompt, string $acao): void
    {
        abort_unless(
            (int) $prompt->user_id === Auth::id(),
            403,
            "Você só pode {$acao} prompts do seu próprio histórico."
        );
    }
}
