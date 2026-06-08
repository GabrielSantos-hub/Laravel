<?php

namespace App\Http\Controllers;

use App\Http\Requests\GeneratePromptRequest;
use App\Models\Architecture;
use App\Models\Framework;
use App\Models\Language;
use App\Models\Prompt;
use App\Models\Template;
use App\Services\PromptGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PromptController extends Controller
{
    public function __construct(
        protected PromptGenerator $promptGenerator
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
        $prompt->load(['template', 'architecture', 'language', 'framework']);
        return view('prompts.show', compact('prompt'));
    }

    public function generate(GeneratePromptRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $template = Template::query()
            ->whereKey($validated['template_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $architecture = Architecture::query()->findOrFail($validated['architecture_id']);
        $language = Language::query()->findOrFail($validated['language_id']);
        $framework = isset($validated['framework_id']) && $validated['framework_id'] !== 'none'
            ? Framework::query()->find($validated['framework_id'])
            : null;

        $output = $this->promptGenerator->render(
            $template,
            $validated['input_text'],
            $language,
            $architecture,
            $framework
        );

        // REATIVADO: Agora vincula corretamente com o usuário logado no banco de dados!
        Prompt::query()->create([
            'user_id' => Auth::id(), 
            'template_id' => $template->id,
            'architecture_id' => $architecture->id,
            'language_id' => $language->id,
            'framework_id' => $framework?->id,
            'input_text' => $validated['input_text'],
            'output_text' => $output,
        ]);

        return redirect()
            ->route('home')
            ->with('sucesso', 'Prompt gerado e salvo no histórico.')
            ->with('last_output', $output);
    }

    public function destroy($id)
    {
        $prompt = Prompt::findOrFail($id);
        $prompt->delete();
        return redirect()->route('home')->with('sucesso', 'Prompt removido do histórico com sucesso!');
    }
}