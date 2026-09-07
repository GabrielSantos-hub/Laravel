<div class="d-flex flex-wrap gap-2 mt-auto">
    <a href="{{ $editUrl }}" class="btn-catalog btn-catalog-edit focus:ring-2 focus:ring-indigo-500 focus:outline-none">
        Editar
    </a>
    <form action="{{ $destroyUrl }}" method="POST" class="m-0"
        onsubmit="return confirm({{ json_encode($destroyConfirm ?? 'Tem certeza que deseja excluir?') }});">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn-catalog btn-catalog-delete focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            Excluir
        </button>
    </form>
</div>
