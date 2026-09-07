@php
    $user = $user ?? auth()->user();
    $size = $size ?? 28;
    $url = $user?->avatarUrl();
@endphp
@if ($url)
    <img src="{{ $url }}" alt="" class="user-avatar" width="{{ $size }}" height="{{ $size }}">
@else
    <svg xmlns="http://www.w3.org/2000/svg" width="{{ $size }}" height="{{ $size }}" fill="currentColor" viewBox="0 0 16 16" class="user-avatar-fallback" aria-hidden="true">
        <path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0" />
        <path fill-rule="evenodd" d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8m8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1" />
    </svg>
@endif
