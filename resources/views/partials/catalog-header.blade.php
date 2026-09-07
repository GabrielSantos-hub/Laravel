<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
    <h3 class="mb-0">{{ $title }}</h3>
    @auth
        @if(auth()->user()->role === 'ADM' && ! empty($actionUrl))
        <a href="{{ $actionUrl }}" class="btn-catalog btn-catalog-primary focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            {{ $actionLabel }}
        </a>
        @endif
    @endauth
</div>
