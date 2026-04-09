<?php

namespace App\Http\Controllers;

use App\Models\Framework;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class FrameworkController extends Controller
{
    public function index()
    {
        //trazendo o nome da linguagem 
        $frameworks = Framework::with('language')->get();
        return view('frameworks.index', compact('frameworks'));
    }

    public function create()
    {
        $languages = Language::all(); // Busca linguagens para o select 
        return view('frameworks.create', compact('languages'));
    }

    public function store(Request $request)
    {
        // 1. VALIDAÇÃO FORA DO TRY/CATCH
        // Se falhar aqui, o Laravel interrompe e volta sozinho para o form com os erros
        $request->validate([
            'nome' => 'required',
            'language_id' => 'required|exists:languages,id'
        ]);

        try {
            // 2. TENTA SALVAR NO BANCO
            Framework::create($request->all());         
            return redirect()->route('frameworks.index')->with('sucesso', 'Framework cadastrado com sucesso!');
        } catch (Exception $e) {
            Log::error('Erro ao inserir framework: ' . $e->getMessage());
            return back()->withErrors('Erro interno ao salvar o framework no banco de dados.');
        }
    }

    public function edit($id)
    {
        $framework = Framework::findOrFail($id);
        $languages = Language::all(); // Busca as linguagens 
        return view('frameworks.edit', compact('framework', 'languages'));
    }

    public function update(Request $request, $id)
    {
        // VALIDAÇÃO 
       $request->validate([
            'nome' => 'required',
            'slug' => 'required|unique:frameworks,slug', 
            'language_id' => 'required|exists:languages,id'
        ]);

        try {
            $framework = Framework::findOrFail($id);
            $framework->update($request->all());
            
            return redirect()->route('frameworks.index')->with('sucesso', 'Framework atualizado com sucesso!');
            
        } catch (Exception $e) {
            Log::error('Erro ao editar framework: ' . $e->getMessage());
            return back()->withErrors('Erro interno ao atualizar o framework.');
        }
    }

    public function destroy($id)
    {
        try {
            $framework = Framework::findOrFail($id);
            $framework->delete();
            
            return redirect()->route('frameworks.index')->with('sucesso', 'Framework excluído com sucesso!');
            
        } catch (Exception $e) {
            Log::error('Erro ao excluir framework: ' . $e->getMessage());
            return back()->withErrors('Erro interno ao tentar excluir o framework.');
        }
    }
}