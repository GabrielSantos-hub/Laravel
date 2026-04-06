<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Produto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class ProdutoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $produtos = Produto::all();
        return view('produto.index', compact('produtos'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $categorias = Categoria::all();
        return view("produto.create", compact('produtos'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try{
            Produto::create($request->all());
        } catch(Exception $e){
            Log::error('Erro ao inserir produto: '. $e->getMessage(), [
                'stack' => $e->getTraceAsString()
            ]);
        }
        return redirect()->route('produtos.index');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $produto = Produto::findOrFail($id);
        return view("produto.show", compact('produto'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $produto = Produto::findOrFail($id);
        $categorias = Categorial::all();
        return view('produto.edit', compact('categorias', 'produto'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try{
            $produto = Produto::findOrFail($id);
            $produto->update($request->all());
        } catch(Exception $e){
            Log::error('Erro ao alterar produto: '. $e->getMessage(), [
                'stack' => $e->getTraceAsString()
            ]);
        }
        return redirect()->route('produtos.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try{
            $produto = Produto::findOrFail($id);
            $produto->delete();
        } catch(Exception $e){
            Log::error('Erro ao excluir produto: '. $e->getMessage(), [
                'stack' => $e->getTraceAsString()
            ]);
        }
        return redirect()->route('produtos.index');
    }
}