@php
    $defs = \App\Models\Achievement::definitions();
@endphp

<div class="grid grid-cols-4 gap-3">
    @foreach ($defs as $code => [$icon, $name, $desc])
        @php
            $locked = !$unlockedCodes->contains($code);
            $unlocked = $unlockedAchievements->firstWhere("code", $code);
        @endphp <div
            class="flex flex-col items-center text-center rounded-lg p-2 {{ $locked ? "opacity-40 grayscale" : "bg-brand/10 border border-brand/20" }}"
            title="{{ $name }}: {{ $desc }}"> <span class="text-2xl">{{ $icon }}</span> <span
                class="mt-1 text-[10px] font-medium leading-tight">{{ $name }}</span> <span
                class="text-[9px] text-text-secondary">{{ $desc }}</span>
            @if (!$locked)
                <span class="mt-1 text-[9px] text-brand">Terbuka</span>
            @else
                <span class="mt-1 text-[9px] text-text-secondary">Terkunci</span>
            @endif
        </div>
    @endforeach
</div>
