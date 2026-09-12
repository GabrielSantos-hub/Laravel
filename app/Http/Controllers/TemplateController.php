<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTemplateRequest;
use App\Http\Requests\UpdateTemplateRequest;
use App\Models\Template;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class TemplateController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth', except: ['index']),
            new Middleware('can:admin', except: ['index']),
        ];
    }

    public function index(): View
    {
        $templates = Template::query()->orderBy('nome')->get();

        $blocos = [
            'todos' => $templates,
            Template::BLOCO_A => $templates->filter(fn (Template $template) => $template->resolveBloco() === Template::BLOCO_A)->values(),
            Template::BLOCO_B => $templates->filter(fn (Template $template) => $template->resolveBloco() === Template::BLOCO_B)->values(),
            Template::BLOCO_C => $templates->filter(fn (Template $template) => $template->resolveBloco() === Template::BLOCO_C)->values(),
        ];

        return view('templates.index', compact('templates', 'blocos'));
    }

    public function create(): View
    {
        return view('templates.create');
    }

    public function store(StoreTemplateRequest $request): RedirectResponse
{
    $data = $request->validated();
    $data['is_active'] = $request->boolean('is_active');
    if (! isset($data['versao']) || $data['versao'] === '') {
        $data['versao'] = '1';
    }

    Template::query()->create($data);

    return redirect()->route('templates.index')->with('sucesso', 'Template salvo.');
}

    public function edit(Template $template): View
    {
        return view('templates.edit', compact('template'));
    }

    public function update(UpdateTemplateRequest $request, Template $template): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        if (! isset($data['versao']) || $data['versao'] === '') {
            $data['versao'] = '1';
        }

        $template->update($data);

        return redirect()->route('templates.index')->with('sucesso', 'Template atualizado.');
    }

    public function destroy(Template $template): RedirectResponse
    {
        $template->delete();
        return redirect()->route('templates.index')->with('sucesso', 'Template removido.');
    }
}   