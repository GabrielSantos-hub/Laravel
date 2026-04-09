<?php

namespace App\Http\Controllers;

use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class LanguageController extends Controller
{
    public function index()
    {
        $languages = \App\Models\Language::all(); // Busca tudo do banco
        return view('languages.index', compact('languages'));
    }

    public function create()
    {
        // Retorna o formulário em branco
        return view('languages.create');
    }

    public function store(Request $request)
    {
        try {
            // Segurança nível Sênior: Validação antes de salvar!
            $request->validate([
                'nome' => 'required|max:100',
                'slug' => 'required|max:100|unique:languages'
            ]);

            Language::create($request->all());
        } catch (Exception $e) {
            Log::error('Erro ao inserir linguagem: ' . $e->getMessage());
        }
        return redirect()->route('languages.index')->with('sucesso', 'Linguagem salva!');
    }

    public function edit($id)
    {
        $language = Language::findOrFail($id);
        return view('languages.edit', compact('language'));
    }

    public function update(Request $request, $id)
    {
        try {
            $language = Language::findOrFail($id);
            $language->update($request->all());
        } catch (Exception $e) {
            Log::error('Erro ao alterar linguagem: ' . $e->getMessage());
        }
        return redirect()->route('languages.index');
    }

    public function destroy($id)
    {
        try {
            $language = Language::findOrFail($id);
            $language->delete();
        } catch (Exception $e) {
            Log::error('Erro ao excluir linguagem: ' . $e->getMessage());
        }
        return redirect()->route('languages.index');
    }
}
