@php
    $user = auth()->user();
@endphp
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name')) · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon-32x32.png') }}" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <script>
        // Default dark; persist toggle via localStorage (sama seperti layouts.app).
        (function () {
            var saved = localStorage.getItem('lbta-theme');
            if (saved === 'light') document.documentElement.classList.remove('dark');
        })();
    </script>
    <link rel="stylesheet" href="{{ asset('css/global.css') }}?v={{ @filemtime(public_path('css/global.css')) }}">
    @yield('head')
</head>
<body class="bg-bg-base text-text-primary h-screen overflow-hidden font-sans antialiased {{ $user?->isDosen() ? 'ctx-dosen' : ($user?->isAdmin() ? 'ctx-admin' : 'ctx-mahasiswa') }}">
    @if (session('success'))
        <div class="absolute top-3 left-3 right-3 z-50 px-4 py-2.5 rounded-xl bg-status-success/10 text-status-success border border-status-success/20 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="absolute top-3 left-3 right-3 z-50 px-4 py-2.5 rounded-xl bg-status-danger/10 text-status-danger border border-status-danger/20 text-sm">{{ session('error') }}</div>
    @endif

    @yield('content')
    @yield('scripts')
</body>
</html>
