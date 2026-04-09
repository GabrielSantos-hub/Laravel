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
        //busca o framework com o nome da linguagem
        $frameworks = Framework::with('language')->get();
        return view('frameworks.index', compact('frameworks'));
    }

    public function create()
    {
        $languages = Language::all(); //select na tela
        return view('frameworks.create', compact('languages'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'nome' => 'required',
                'language_id' => 'required|exists:languages,id'
            ]);

            Framework::create($request->all());
        } catch (Exception $e) {
            Log::error('Erro ao inserir framework: ' . $e->getMessage());
        }
        
        return redirect()->route('frameworks.index');
    }

    public function edit($id)
    {
        $framework = Framework::findOrFail($id);
        $languages = Language::all(); // Busca as linguagens para popular
        return view('frameworks.edit', compact('framework', 'languages'));
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'nome' => 'required',
                'language_id' => 'required|exists:languages,id'
            ]);

            $framework = Framework::findOrFail($id);
            $framework->update($request->all());
        } catch (Exception $e) {
            Log::error('Erro ao editar framework: ' . $e->getMessage());
        }
        
        return redirect()->route('frameworks.index');
    }

    public function destroy($id)
    {
        try {
            $framework = Framework::findOrFail($id);
            $framework->delete();
        } catch (Exception $e) {
            Log::error('Erro ao excluir framework: ' . $e->getMessage());
        }
        
        return redirect()->route('frameworks.index');
    }
}