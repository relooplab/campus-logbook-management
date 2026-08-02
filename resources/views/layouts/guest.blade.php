@php
    $inst = \App\Models\Institution::active();
@endphp
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Masuk') · {{ $inst->app_name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                        heading: ['Plus Jakarta Sans', 'Inter', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        bg: { base: 'rgb(var(--bg-base) / <alpha-value>)', surface: 'rgb(var(--bg-surface) / <alpha-value>)', panel: 'rgb(var(--bg-panel) / <alpha-value>)', hover: 'rgb(var(--bg-hover) / <alpha-value>)' },
                        border: { DEFAULT: 'rgb(var(--border) / <alpha-value>)' },
                        text: { primary: 'rgb(var(--text-primary) / <alpha-value>)', secondary: 'rgb(var(--text-secondary) / <alpha-value>)' },
                        accent: { blue: 'rgb(var(--accent-blue) / <alpha-value>)', orange: 'rgb(var(--accent-orange) / <alpha-value>)', teal: 'rgb(var(--accent-teal) / <alpha-value>)', purple: 'rgb(var(--accent-purple) / <alpha-value>)' },
                        status: { success: 'rgb(var(--status-success) / <alpha-value>)', danger: 'rgb(var(--status-danger) / <alpha-value>)', info: 'rgb(var(--status-info) / <alpha-value>)', pending: 'rgb(var(--status-pending) / <alpha-value>)' },
                        'card-inverse': 'rgb(var(--card-inverse) / <alpha-value>)',
                    },
                    borderRadius: { card: '20px' },
                },
            },
        };
        (function () {
            var saved = localStorage.getItem('lbta-theme');
            if (saved === 'light') document.documentElement.classList.remove('dark');
        })();
    </script>
    <link rel="stylesheet" href="{{ asset('css/global.css') }}">
</head>
<body class="bg-bg-base text-text-primary min-h-screen flex items-center justify-center p-4 font-sans antialiased">
    <div class="w-full max-w-md">
        <div class="text-center mb-6">
            <div class="inline-flex w-14 h-14 rounded-2xl bg-accent-blue/15 text-accent-blue items-center justify-center mb-3">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            </div>
            <h1 class="font-heading font-extrabold text-2xl text-text-primary">{{ $inst->app_name }}</h1>
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
    </div>
    @yield('guest-scripts')
</body>
</html>
