@php
    $inst = \App\Models\Institution::active();
@endphp
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Masuk') · {{ $inst->app_name }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon-32x32.png') }}" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block" rel="stylesheet" />
    <style>
        .material-symbols-outlined {
            user-select: none;
            vertical-align: middle;
            font-variation-settings: 'FILL' 0, 'wght' 500, 'GRAD' 0, 'opsz' 24;
        }
        .icon-sm { font-size: 16px; }
        .icon-md { font-size: 20px; }
        .icon-lg { font-size: 24px; }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        (function () {
            var saved = localStorage.getItem('lbta-theme');
            if (saved === 'light') document.documentElement.classList.remove('dark');
        })();
    </script>
    <link rel="stylesheet" href="{{ asset('css/global.css') }}?v={{ @filemtime(public_path('css/global.css')) }}">
</head>
<body class="bg-bg-base text-text-primary min-h-screen flex items-center justify-center p-4 font-sans antialiased ctx-mahasiswa">
    <div class="w-full max-w-md">
        <div class="text-center mb-6">
            @include('partials.wordmark', [
                'markSize' => 'w-14 h-14',
                'accent' => 'text-brand',
            ])
            <p class="text-sm text-text-secondary mt-1">Aplikasi pencatatan &amp; monitoring bimbingan Tugas Akhir mahasiswa</p>
            <div class="mt-3 inline-block px-4 py-2 rounded-card bg-bg-surface border border-border">
                <p class="font-semibold text-text-primary">{{ $inst->institution_name }}</p>
                @if ($inst->faculty || $inst->study_program)
                    <p class="text-xs text-text-secondary">{{ trim($inst->faculty . ' ' . ($inst->study_program ? '— ' . $inst->study_program : '')) }}</p>
                @endif
            </div>
        </div>
        <div class="bg-bg-surface rounded-card shadow-lg border border-border p-6">
            @yield('guest-content')
        </div>
        <div class="mt-6 flex items-center justify-center gap-3 text-sm">
            <a href="https://github.com/relooplab/campus-logbook-management" target="_blank" rel="noopener"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-text-secondary hover:text-text-primary hover:bg-bg-hover transition-colors"
                title="Lihat kode sumber aplikasi di GitHub">
                <span class="material-symbols-outlined icon-sm text-accent-purple">code</span> GitHub
            </a>
            <a href="https://reloop.notion.site/3b1155a221e880829514df5d0a8dcfd6" target="_blank" rel="noopener"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl font-semibold bg-status-pending/15 text-status-pending hover:bg-status-pending/25 border border-status-pending/30 transition-colors"
                title="Laporkan masalah atau kirim ide untuk pengembangan aplikasi">
                <span class="material-symbols-outlined icon-sm text-status-pending">feedback</span> Kirim Masukan
            </a>
        </div>
    </div>
    @yield('guest-scripts')
</body>
</html>
