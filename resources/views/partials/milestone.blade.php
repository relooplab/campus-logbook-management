@php
    // Label fase: prioritas dari controller ($faseLabels), fallback ke konstanta.
    $labels = $faseLabels ?? \App\Models\MahasiswaTa::FASES;
    if (empty($labels) && array_intersect($faseKeys ?? [], array_keys(\App\Models\MahasiswaTa::FASES_KP))) {
        $labels = \App\Models\MahasiswaTa::FASES_KP;
    }
@endphp

<div class="flex flex-wrap items-center gap-1">
    @foreach ($faseKeys as $i => $key)
        @php $state = $i < $faseIndex ? 'done' : ($i === $faseIndex ? 'active' : 'todo'); @endphp <div class="flex items-center">
            <div class="flex flex-col items-center text-center w-20 sm:w-24">
                @if ($state === "done")
                    <span
                        class="h-8 w-8 rounded-full bg-brand text-white flex items-center justify-center shadow"><span class="material-symbols-outlined icon-sm">check</span></span>
                @elseif ($state === "active")
                    <span
                        class="h-8 w-8 rounded-full bg-brand text-white flex items-center justify-center shadow animate-pulse ring-2 ring-brand ring-offset-2 ring-offset-bg-panel">●</span>
                @else
                    <span
                        class="h-8 w-8 rounded-full bg-bg-panel text-text-secondary flex items-center justify-center">○</span>
                @endif <span
                    class="mt-1 text-[11px] leading-tight {{ $state === "active" ? "font-semibold text-brand" : ($state === "done" ? "text-brand" : "text-text-secondary") }}">
                    {{ $labels[$key] ?? $key }} </span>
            </div>
            @if ($i < count($faseKeys) - 1)
                <div class="w-6 h-0.5 mb-5 {{ $i < $faseIndex ? "bg-brand" : "bg-bg-panel" }}"></div>
            @endif
        </div>
    @endforeach
</div>