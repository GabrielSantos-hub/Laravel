<?php

namespace App\Http\Controllers;

use App\Models\Architecture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ArchitectureController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('role.adm', except: ['index']),
        ];
    }

    public function index()
    {
        $architectures = Architecture::all(); 
        return view('architectures.index', compact('architectures'));
    }

    public function create()
    {
        return view('architectures.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome' => 'required|max:100',
            'descricao' => 'required'
        ]);

        try {
            Architecture::create($validated);
            return redirect()->route('architectures.index')->with('sucesso', 'Arquitetura salva com sucesso!');
        } catch (Exception $e) {
            Log::error('Erro ao inserir arquitetura: ' . $e->getMessage());
            return back()->withErrors('Erro ao salvar arquitetura.');
        }
    }

    public function edit($id)
    {
        $architecture = Architecture::findOrFail($id);
        return view('architectures.edit', compact('architecture'));
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'nome' => 'required|max:100',
            'descricao' => 'required'
        ]);

        try {
            $architecture = Architecture::findOrFail($id);
            $architecture->update($validated);
            return redirect()->route('architectures.index')->with('sucesso', 'Arquitetura atualizada!');
        } catch (Exception $e) {
            Log::error('Erro ao alterar arquitetura: ' . $e->getMessage());
            return back()->withErrors('Erro ao atualizar arquitetura.');
        }
    }

    public function destroy($id)
    {
        try {
            $architecture = Architecture::findOrFail($id);
            $architecture->delete();
            return redirect()->route('architectures.index')->with('sucesso', 'Arquitetura removida!');
        } catch (Exception $e) {
            Log::error('Erro ao excluir arquitetura: ' . $e->getMessage());
            return back()->withErrors('Erro ao remover arquitetura.');
        }
    }
}