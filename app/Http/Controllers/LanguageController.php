<?php

namespace App\Http\Controllers;

use App\Models\Language;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class LanguageController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('role.adm', except: ['index', 'show']),
        ];
    }

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
        // Validação movida para FORA do try/catch
        $validated = $request->validate([
            'nome' => 'required|max:100',
            'slug' => 'required|max:100|unique:languages,slug'
        ]);

        try {
            Language::create($validated); // Salvando apenas dados validados por segurança
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
            'nome' => 'required|max:100',
            'slug' => 'required|max:100|unique:languages,slug,' . $language->id
        ]);

        try {
            $language->update($validated);
            return redirect()->route('languages.index')->with('sucesso', 'Linguagem atualizada!');
        } catch (Exception $e) {
            Log::error('Erro ao alterar linguagem: ' . $e->getMessage());
            return back()->withErrors('Erro interno ao atualizar.');
        }
    }

    public function destroy($id)
    {
        try {
            $language = Language::findOrFail($id);
            $language->delete();
            return redirect()->route('languages.index')->with('sucesso', 'Linguagem removida!');
        } catch (Exception $e) {
            Log::error('Erro ao excluir linguagem: ' . $e->getMessage());
            return back()->withErrors('Não é possível excluir uma linguagem vinculada a um framework.');
        }
    }
}