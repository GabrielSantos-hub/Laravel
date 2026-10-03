<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Services\Security\AdminAuditor;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class LanguageController extends Controller
{
    public function index()
    {
        $languages = Language::all(); 
        return view('languages.index', compact('languages'));
    }

    public function create()
    {
        return view('languages.create');
    }

    public function store(Request $request)
{
    $validated = $request->validate([
        'nome' => 'required|string|max:100|unique:languages,nome',
        'slug' => 'required|string|max:100|alpha_dash|unique:languages,slug',
    ]);

    try {
        $language = Language::create($validated);
        app(AdminAuditor::class)->record('admin_language_created', $language, ['nome' => $language->nome]); 
        return redirect()->route('languages.index')->with('sucesso', 'Linguagem salva com sucesso!');
    } catch (Exception $e) {
        Log::error('Erro ao inserir linguagem: ' . $e->getMessage());
        return back()->withErrors('Erro interno ao salvar a linguagem.');
    }
}

    public function show(Language $language): View
    {
        return view('languages.show', compact('language'));
    }

    public function edit($id)
    {
        $language = Language::findOrFail($id);
        return view('languages.edit', compact('language'));
    }

    public function update(Request $request, $id)
    {
        $language = Language::findOrFail($id);

        $validated = $request->validate([
            'nome' => 'required|string|max:100',
            'slug' => 'required|string|max:100|alpha_dash|unique:languages,slug,'.$language->id,
        ]);

        try {
            $language->update($validated);
            app(AdminAuditor::class)->record('admin_language_updated', $language, ['nome' => $language->nome]);
            return redirect()->route('languages.index')->with('sucesso', 'Linguagem atualizada!');
        } catch (Exception $e) {
            Log::error('Erro ao alterar linguagem: ' . $e->getMessage());
            return back()->withErrors('Erro interno ao atualizar.');
        }
    }

    public function destroy($id)
    {
        $language = Language::findOrFail($id);

        if ($language->frameworks()->exists()) {
            return back()->withErrors('Não é possível excluir uma linguagem vinculada a um framework.');
        }

        if ($language->templates()->exists()) {
            return back()->withErrors('Não é possível excluir uma linguagem vinculada a um template.');
        }

        try {
            $id = $language->id;
            $language->delete();
            app(AdminAuditor::class)->record('admin_language_deleted', null, ['target_id' => $id]);
        } catch (QueryException $e) {
            Log::error('Erro ao excluir linguagem: '.$e->getMessage());

            return back()->withErrors('Não foi possível excluir a linguagem.');
        }

        return redirect()->route('languages.index')->with('sucesso', 'Linguagem removida!');
    }
}