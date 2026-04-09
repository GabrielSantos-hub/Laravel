<?php

namespace App\Http\Controllers;

use App\Models\Architecture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class ArchitectureController extends Controller
{
    public function index()
    {
        $architectures = Architecture::all(); // Busca todas as arquiteturas do banco
        return view('architectures.index', compact('architectures'));
    }

    public function create()
    {
        // Retorna o formulário em branco para cadastro
        return view('architectures.create');
    }

    public function store(Request $request)
    {
        try {
            // Validação dos dados que chegam do formulário
            $request->validate([
                'nome' => 'required|max:100',
                'descricao' => 'required'
            ]);

            Architecture::create($request->all());
        } catch (Exception $e) {
            Log::error('Erro ao inserir arquitetura: ' . $e->getMessage());
        }
        
        return redirect()->route('architectures.index')->with('sucesso', 'Arquitetura salva com sucesso!');
    }

    public function edit($id)
    {
        $architecture = Architecture::findOrFail($id);
        return view('architectures.edit', compact('architecture'));
    }

    public function update(Request $request, $id)
    {
        try {
            // Boa prática: Validar também na atualização
            $request->validate([
                'nome' => 'required|max:100',
                'descricao' => 'required'
            ]);

            $architecture = Architecture::findOrFail($id);
            $architecture->update($request->all());
        } catch (Exception $e) {
            Log::error('Erro ao alterar arquitetura: ' . $e->getMessage());
        }
        
        return redirect()->route('architectures.index')->with('sucesso', 'Arquitetura atualizada!');
    }

    public function destroy($id)
    {
        try {
            $architecture = Architecture::findOrFail($id);
            $architecture->delete();
        } catch (Exception $e) {
            Log::error('Erro ao excluir arquitetura: ' . $e->getMessage());
        }
        
        return redirect()->route('architectures.index')->with('sucesso', 'Arquitetura removida!');
    }
}