@php
    $defs = \App\Models\Achievement::definitions();

    // Palet warna khas per achievement.
    // Setiap entri: [bg/border/text light, bg/border/text dark, label "Terbuka" light+dark].
    // Saat locked: grayscale netral (bg-panel/border) agar tidak menonjol.
    $achvColors = [
        'langkah_pertama' => [
            'bg-[#FEF3C7] text-[#B45309] border-[#FDE68A]',
            'dark:bg-[#3A2C10] dark:text-[#FCD34D] dark:border-[#5C4A1A]',
            'text-[#B45309] dark:text-[#FCD34D]',
        ],
        'konsisten' => [
            'bg-[#ECFCCB] text-[#3F6212] border-[#D9F99D]',
            'dark:bg-[#2B3310] dark:text-[#D9F99D] dark:border-[#4A551C]',
            'text-[#3F6212] dark:text-[#D9F99D]',
        ],
        'zero_revisi' => [
            'bg-[#CCFBF1] text-[#0F766E] border-[#99F6E4]',
            'dark:bg-[#102C2A] dark:text-[#5EEAD4] dark:border-[#1F4E48]',
            'text-[#0F766E] dark:text-[#5EEAD4]',
        ],
        'comeback' => [
            'bg-[#FFE4D1] text-[#C2410C] border-[#FDBA74]',
            'dark:bg-[#3A1E14] dark:text-[#FDBA74] dark:border-[#5C3620]',
            'text-[#C2410C] dark:text-[#FDBA74]',
        ],
        'setengah_jalan' => [
            'bg-[#DBEAFE] text-[#1D4ED8] border-[#BFDBFE]',
            'dark:bg-[#142B4E] dark:text-[#93C5FD] dark:border-[#234873]',
            'text-[#1D4ED8] dark:text-[#93C5FD]',
        ],
        'garis_akhir' => [
            'bg-[#EDE9FE] text-[#6D28D9] border-[#DDD6FE]',
            'dark:bg-[#2E2345] dark:text-[#C4B5FD] dark:border-[#4C3E73]',
            'text-[#6D28D9] dark:text-[#C4B5FD]',
        ],
        'responsif' => [
            'bg-[#CFFAFE] text-[#0E7490] border-[#A5F3FC]',
            'dark:bg-[#0E3038] dark:text-[#67E8F9] dark:border-[#1C4F5C]',
            'text-[#0E7490] dark:text-[#67E8F9]',
        ],
        'tepat_waktu' => [
            'bg-[#FCE7F3] text-[#BE185D] border-[#FBCFE8]',
            'dark:bg-[#3A1E33] dark:text-[#F9A8D4] dark:border-[#5C3350]',
            'text-[#BE185D] dark:text-[#F9A8D4]',
        ],
    ];
@endphp

<div class="grid grid-cols-4 gap-3">
    @foreach ($defs as $code => [$icon, $name, $desc])
        @php
            $locked = !$unlockedCodes->contains($code);
            $colors = $achvColors[$code] ?? null;
        @endphp
        <div
            class="flex flex-col items-center text-center rounded-lg p-2 border
                {{ $locked ? 'opacity-60 grayscale bg-bg-panel border-border' : ($colors[0] . ' ' . $colors[1]) }}"
            title="{{ $name }}: {{ $desc }}">
            <span class="text-2xl">{{ $icon }}</span>
            <span class="mt-1 text-[10px] font-medium leading-tight">{{ $name }}</span>
            <span class="text-[9px] text-text-secondary">{{ $desc }}</span>
            @if (!$locked && $colors)
                <span class="mt-1 text-[9px] {{ $colors[2] }}">Terbuka</span>
            @else
                <span class="mt-1 text-[9px] text-text-secondary">Terkunci</span>
            @endif
        </div>
    @endforeach
</div>