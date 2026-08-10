@php
    $inst = \App\Models\Institution::active();
    $appName = optional($inst)->app_name ?: 'Campus Logbook Management';
@endphp
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code', '500') · {{ $appName }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon-32x32.png') }}" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block" rel="stylesheet" />
    <style>
        .material-symbols-outlined { user-select: none; vertical-align: middle; font-variation-settings: 'FILL' 0, 'wght' 500, 'GRAD' 0, 'opsz' 24; }
        .icon-sm { font-size: 16px; }
        .icon-md { font-size: 20px; }
        .icon-lg { font-size: 24px; }
    </style>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Plus Jakarta Sans', 'ui-sans-serif', 'system-ui', 'sans-serif'], heading: ['Plus Jakarta Sans', 'ui-sans-serif', 'system-ui', 'sans-serif'], mono: ['IBM Plex Mono', 'ui-monospace', 'monospace'] },
                    colors: {
                        bg: { base: 'rgb(var(--bg-base) / <alpha-value>)', surface: 'rgb(var(--bg-surface) / <alpha-value>)', panel: 'rgb(var(--bg-panel) / <alpha-value>)', hover: 'rgb(var(--bg-hover) / <alpha-value>)' },
                        border: { DEFAULT: 'rgb(var(--border) / <alpha-value>)' },
                        text: { primary: 'rgb(var(--text-primary) / <alpha-value>)', secondary: 'rgb(var(--text-secondary) / <alpha-value>)' },
                        brand: { DEFAULT: 'rgb(var(--brand) / <alpha-value>)', hover: 'rgb(var(--brand-hover) / <alpha-value>)', light: 'rgb(var(--brand-light) / <alpha-value>)', fill: 'rgb(var(--brand-fill) / <alpha-value>)', 'fill-hover': 'rgb(var(--brand-fill-hover) / <alpha-value>)' },
                        accent: { blue: 'rgb(var(--accent-blue) / <alpha-value>)', orange: 'rgb(var(--accent-orange) / <alpha-value>)', teal: 'rgb(var(--accent-teal) / <alpha-value>)', purple: 'rgb(var(--accent-purple) / <alpha-value>)' },
                        sand: { DEFAULT: 'rgb(var(--sand) / <alpha-value>)', light: 'rgb(var(--sand-light) / <alpha-value>)' },
                        status: { success: 'rgb(var(--status-success) / <alpha-value>)', danger: 'rgb(var(--status-danger) / <alpha-value>)', info: 'rgb(var(--status-info) / <alpha-value>)', pending: 'rgb(var(--status-pending) / <alpha-value>)' },
                    },
                    borderRadius: { card: '20px', control: '10px' },
                },
            },
        };
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
        </div>
        <div class="bg-bg-surface rounded-card shadow-lg border border-border p-8 text-center">
            <div class="inline-flex w-16 h-16 rounded-2xl bg-status-danger/10 text-status-danger items-center justify-center mb-4">
                <span class="material-symbols-outlined icon-lg">@yield('icon', 'error')</span>
            </div>
            <p class="font-heading font-extrabold text-5xl text-text-primary tabular-nums">@yield('code', '500')</p>
            <h1 class="mt-2 text-lg font-semibold text-text-primary">@yield('title', 'Terjadi Kesalahan')</h1>
            <p class="mt-2 text-sm text-text-secondary">@yield('message', 'Maaf, terjadi sesuatu yang tidak terduga. Silakan coba lagi.')</p>
            <div class="mt-6 flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ route('dashboard') }}"
                    class="inline-flex items-center justify-center gap-1.5 px-5 py-2.5 rounded-control bg-brand hover:bg-brand-hover text-[#0b1420] text-sm font-semibold">
                    <span class="material-symbols-outlined icon-sm text-accent-blue">dashboard</span> Ke Dashboard
                </a>
                <a href="{{ url('/') }}"
                    class="inline-flex items-center justify-center gap-1.5 px-5 py-2.5 rounded-xl bg-bg-hover text-text-primary hover:bg-border text-sm font-medium">
                    <span class="material-symbols-outlined icon-sm text-accent-teal">home</span> Ke Beranda
                </a>
            </div>
        </div>
    </div>
</body>
</html>
