<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTemplateRequest;
use App\Http\Requests\UpdateTemplateRequest;
use App\Models\Architecture;
use App\Models\Template;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TemplateController extends Controller
{
    public function index(): View
    {
        $templates = Template::query()
            ->with('architecture')
            ->orderBy('nome')
            ->get();

        return view('templates.index', compact('templates'));
    }

    public function create(): View
    {
        $architectures = Architecture::query()->orderBy('nome')->get();

        return view('templates.create', compact('architectures'));
    }

    public function store(StoreTemplateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        if (! isset($data['versao']) || $data['versao'] === '') {
            $data['versao'] = '1';
        }

        Template::query()->create($data);

        return redirect()
            ->route('templates.index')
            ->with('sucesso', 'Template salvo.');
    }

    public function edit(Template $template): View
    {
        $architectures = Architecture::query()->orderBy('nome')->get();

        return view('templates.edit', compact('template', 'architectures'));
    }

    public function update(UpdateTemplateRequest $request, Template $template): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        if (! isset($data['versao']) || $data['versao'] === '') {
            $data['versao'] = '1';
        }

        $template->update($data);

        return redirect()
            ->route('templates.index')
            ->with('sucesso', 'Template atualizado.');
    }

    public function destroy(Template $template): RedirectResponse
    {
        $template->delete();

        return redirect()
            ->route('templates.index')
            ->with('sucesso', 'Template removido.');
    }
}
