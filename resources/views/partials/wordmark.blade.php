{{-- Wordmark (brand guideline §01): mark kotak radius 14px berisi ikon checklist,
     diikuti wordmark dinamis (kata terakhir nama aplikasi berwarna accent).
     Baca nama dari $name (param) atau fallback Institution::active()->app_name.
     Opsional: $accent (default brand), $markSize (default w-14 h-14), $textAlign. --}}
@php
    $accent = $accent ?? 'text-brand';
    $markSize = $markSize ?? 'w-14 h-14';
    $textAlign = $textAlign ?? 'text-center';
    $appName = $name ?? optional(\App\Models\Institution::active())->app_name ?: 'Campus Logbook Management';
    $words = preg_split('/\s+/', trim($appName));
    $lastWord = (string) array_pop($words);
    $firstWords = implode(' ', $words);
@endphp
<div class="inline-flex flex-col items-center {{ $textAlign }}">
    <div class="inline-flex {{ $markSize }} rounded-[14px] bg-brand-light {{ $accent }} items-center justify-center mb-3 p-3">
        @include('partials.logo-mark')
    </div>
    <span class="font-heading font-extrabold text-2xl text-text-primary">@if ($firstWords !== '')<span>{{ $firstWords }}</span> <span class="{{ $accent }}">{{ $lastWord }}</span>@else<span class="{{ $accent }}">{{ $lastWord }}</span>@endif</span>
</div>
