@props(['title' => '', 'subtitle' => ''])
{{-- Header halaman konsisten: judul + subtitle + area aksi (kanan). --}}
<div class="flex flex-wrap items-center justify-between gap-3">
    <div class="min-w-0">
        @isset($subtitle)
            <p class="text-xs font-medium uppercase tracking-widest text-text-secondary mb-0.5">{{ $subtitle }}</p>
        @endisset
        <h1 class="font-heading font-bold text-2xl text-text-primary">{{ $title }}</h1>
    </div>
    @isset($actions)
        <div class="flex flex-wrap gap-2 w-full sm:w-auto">{{ $actions }}</div>
    @endisset
</div>
