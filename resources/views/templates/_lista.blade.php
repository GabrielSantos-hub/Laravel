@if ($templates->isEmpty())
    <p class="text-muted mb-0">Nenhum template nesta categoria.</p>
@else
    <div class="catalog-grid grid grid-cols-1 md:grid-cols-3 gap-4">
        @foreach ($templates as $t)
        <article class="catalog-card">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <h4 class="h5 mb-0">{{ $t->nome }}</h4>
                @include('templates._badge', ['template' => $t])
            </div>
            <p class="mb-1 d-flex flex-wrap gap-2">
                <span class="catalog-tag">Versão {{ $t->versao }}</span>
                <span class="catalog-tag">{{ $t->is_active ? 'Ativo' : 'Inativo' }}</span>
            </p>
            @if ($t->descricao)
                <p class="small text-muted mb-2">{{ \Illuminate\Support\Str::limit($t->descricao, 110) }}</p>
            @endif
            {{-- O template é escolhido pelo pipeline a partir do texto do usuário,
                 então o catálogo é só consulta para quem não é administrador. --}}
            @auth
                @if(auth()->user()->role === 'ADM')
                    @include('partials.catalog-admin-actions', [
                        'editUrl' => route('templates.edit', $t),
                        'destroyUrl' => route('templates.destroy', $t),
                        'destroyConfirm' => 'Excluir este template?',
                    ])
                @endif
            @endauth
        </article>
        @endforeach
    </div>
@endif
