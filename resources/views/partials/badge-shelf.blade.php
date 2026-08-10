@php
    $defs = \App\Models\Achievement::definitions();

    // Palet warna khas per achievement.
    // Setiap entri: [bg/border/text light, bg/border/text dark, label "Terbuka" light+dark].
    // Saat locked: kartu netral + ikon grayscale (opacity-50), tidak clickable (cursor-default).
    $achvColors = [
        'langkah_pertama' => [
            'bg-accent-orange/15 text-accent-orange border-accent-orange/30',
            'dark:bg-accent-orange/15 dark:text-accent-orange dark:border-accent-orange/30',
            'text-accent-orange',
        ],
        'konsisten' => [
            'bg-status-success/15 text-status-success border-status-success/30',
            'dark:bg-status-success/15 dark:text-status-success dark:border-status-success/30',
            'text-status-success',
        ],
        'zero_revisi' => [
            'bg-accent-teal/15 text-accent-teal border-accent-teal/30',
            'dark:bg-accent-teal/15 dark:text-accent-teal dark:border-accent-teal/30',
            'text-accent-teal',
        ],
        'comeback' => [
            'bg-accent-orange/15 text-accent-orange border-accent-orange/30',
            'dark:bg-accent-orange/15 dark:text-accent-orange dark:border-accent-orange/30',
            'text-accent-orange',
        ],
        'setengah_jalan' => [
            'bg-accent-blue/15 text-accent-blue border-accent-blue/30',
            'dark:bg-accent-blue/15 dark:text-accent-blue dark:border-accent-blue/30',
            'text-accent-blue',
        ],
        'garis_akhir' => [
            'bg-accent-purple/15 text-accent-purple border-accent-purple/30',
            'dark:bg-accent-purple/15 dark:text-accent-purple dark:border-accent-purple/30',
            'text-accent-purple',
        ],
        'responsif' => [
            'bg-accent-teal/15 text-accent-teal border-accent-teal/30',
            'dark:bg-accent-teal/15 dark:text-accent-teal dark:border-accent-teal/30',
            'text-accent-teal',
        ],
        'tepat_waktu' => [
            'bg-status-pending/15 text-status-pending border-status-pending/30',
            'dark:bg-status-pending/15 dark:text-status-pending dark:border-status-pending/30',
            'text-status-pending',
        ],
    ];
@endphp

<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
    @foreach ($defs as $code => [$icon, $name, $desc])
        @php
            $locked = !$unlockedCodes->contains($code);
            $colors = $achvColors[$code] ?? null;
        @endphp
        <div
            class="flex flex-col items-center text-center rounded-lg p-2 border
                {{ $locked ? 'cursor-default bg-bg-panel border-border' : ($colors[0] . ' ' . $colors[1]) }}"
            title="{{ $name }}: {{ $desc }}">
            <span class="text-2xl {{ $locked ? 'opacity-50 grayscale' : '' }}">{{ $icon }}</span>
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