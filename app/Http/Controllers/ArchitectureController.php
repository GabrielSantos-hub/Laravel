<?php

namespace App\Http\Controllers;

use App\Models\Architecture;
use App\Services\Security\AdminAuditor;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class ArchitectureController extends Controller
{
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
        'nome' => 'required|string|max:100|unique:architectures,nome',
        'descricao' => 'required|string|max:5000',
    ]);

    try {
        $architecture = Architecture::create($validated);
        app(AdminAuditor::class)->record('admin_architecture_created', $architecture, ['nome' => $architecture->nome]);
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
            'nome' => 'required|string|max:100',
            'descricao' => 'required|string|max:5000',
        ]);

        try {
            $architecture = Architecture::findOrFail($id);
            $architecture->update($validated);
            app(AdminAuditor::class)->record('admin_architecture_updated', $architecture, ['nome' => $architecture->nome]);
            return redirect()->route('architectures.index')->with('sucesso', 'Arquitetura atualizada!');
        } catch (Exception $e) {
            Log::error('Erro ao alterar arquitetura: ' . $e->getMessage());
            return back()->withErrors('Erro ao atualizar arquitetura.');
        }
    }

    public function destroy($id)
    {
        $architecture = Architecture::findOrFail($id);

        if ($architecture->templates()->exists()) {
            return back()->withErrors('Não é possível excluir uma arquitetura vinculada a um template.');
        }

        try {
            $idAlvo = $architecture->id;
            $architecture->delete();
            app(AdminAuditor::class)->record('admin_architecture_deleted', null, ['target_id' => $idAlvo]);
        } catch (QueryException $e) {
            Log::error('Erro ao excluir arquitetura: '.$e->getMessage());

            return back()->withErrors('Não foi possível excluir a arquitetura.');
        }

        return redirect()->route('architectures.index')->with('sucesso', 'Arquitetura removida!');
    }
}