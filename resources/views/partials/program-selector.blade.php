@php
    $programs = \App\Support\ProgramContext::programs(auth()->user());
    $currentTa = $ta ?? null;
@endphp

@if ($programs->count() > 1)
    <div class="flex flex-wrap gap-2">
        @foreach ($programs as $p)
            <a href="{{ $route ? route($route, array_merge($routeParams ?? [], ['program' => $p->jenis])) : '#' }}"
                class="px-4 py-2 rounded-xl text-sm font-medium border transition-colors
                {{ $currentTa && $currentTa->id === $p->id ? 'bg-brand-fill text-white border-brand-fill' : 'bg-bg-surface text-text-secondary border-border hover:bg-bg-hover' }}">
                {{ $p->jenisLabel() }}
                @if ($p->status_ta === \App\Models\MahasiswaTa::STATUS_AKTIF)
                    <span class="ml-1 text-xs opacity-80">(aktif)</span>
                @endif
            </a>
        @endforeach
    </div>
@endif