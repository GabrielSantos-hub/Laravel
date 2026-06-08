<?php

namespace App\Http\Controllers;

use App\Models\Framework;
use App\Models\Language;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
// Importações necessárias para a proteção no Laravel 11:
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class FrameworkController extends Controller implements HasMiddleware
{
    // Proteção nativa do Laravel 11: Bloqueia tudo com role.adm, EXCETO o index
    public static function middleware(): array
    {
        return [
            new Middleware('role.adm', except: ['index']),
        ];
    }

    public function index()
    {
        $frameworks = Framework::with('language')->get();
        return view('frameworks.index', compact('frameworks'));
    }

    public function create()
    {
        $languages = Language::all(); 
        return view('frameworks.create', compact('languages'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:100',
            'slug' => 'required|string|max:100|unique:frameworks,slug',
            'language_id' => 'required|exists:languages,id',
        ]);

        try {
            Framework::create($validated);
            return redirect()->route('frameworks.index')->with('sucesso', 'Framework cadastrado com sucesso!');
        } catch (Exception $e) {
            Log::error('Erro ao inserir framework: ' . $e->getMessage());
            return back()->withErrors('Erro interno ao salvar o framework no banco de dados.');
        }
    }

    public function edit($id)
    {
        $framework = Framework::findOrFail($id);
        $languages = Language::all(); 
        return view('frameworks.edit', compact('framework', 'languages'));
    }

    public function update(Request $request, $id)
    {
        $framework = Framework::findOrFail($id);

        $validated = $request->validate([
            'nome' => 'required|string|max:100',
            'slug' => ['required', 'string', 'max:100', Rule::unique('frameworks', 'slug')->ignore($framework->id)],
            'language_id' => 'required|exists:languages,id',
        ]);

        try {
            $framework->update($validated);
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