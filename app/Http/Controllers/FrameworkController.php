<?php

namespace App\Http\Controllers;

use App\Models\Framework;
use App\Models\Language;
use Illuminate\Http\Request;

class FrameworkController extends Controller
{
    public function index()
    {
        // Eager Loading (with): busca o framework já trazendo o nome da linguagem
        $frameworks = Framework::with('language')->get();
        return view('frameworks.index', compact('frameworks'));
    }

    public function create()
    {
        $languages = Language::all(); // Precisamos disso para o select na tela
        return view('frameworks.create', compact('languages'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nome' => 'required',
            'language_id' => 'required|exists:languages,id'
        ]);

        Framework::create($request->all());
        return redirect()->route('frameworks.index');
    }
}