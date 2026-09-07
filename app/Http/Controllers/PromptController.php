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
        return view('prompts.index', [
            'architectures' => Architecture::query()->orderBy('nome')->get(),
            'languages' => Language::query()->orderBy('nome')->get(),
            'frameworks' => Framework::query()->with('language')->orderBy('nome')->get(),
            'templates' => Template::query()
                ->where('is_active', true)
                ->orderBy('nome')
                ->get(),
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

        try {
            $resultado = $this->pipeline->generate(
                $validated['user_input'],
                $validated['template_id'] ?? null
            );
        } catch (InvalidIntentException $e) {
            throw ValidationException::withMessages(['user_input' => $e->getMessage()]);
        } catch (NoCompatibleTemplateException $e) {
            throw ValidationException::withMessages([
                ($e->wasManualSelection() ? 'template_id' : 'user_input') => $e->getMessage(),
            ]);
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
            ->with('last_output', $resultado->prompt);
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
