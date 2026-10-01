<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#14161c">
    <title>{{ $appName }} — Bimbingan TA &amp; KP Terstruktur</title>
    <meta name="description" content="Campus Logbook membantu mahasiswa, dosen pembimbing, dan penguji mengelola logbook, review, revisi, progres, dan dokumen TA/KP dalam satu platform.">
    <link rel="canonical" href="{{ route('home') }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="id_ID">
    <meta property="og:title" content="{{ $appName }} — Bimbingan TA &amp; KP Terstruktur">
    <meta property="og:description" content="Campus Logbook membantu mahasiswa, dosen pembimbing, dan penguji mengelola logbook, review, revisi, progres, dan dokumen TA/KP dalam satu platform.">
    <meta property="og:url" content="{{ route('home') }}">
    <meta property="og:image" content="{{ asset('images/landing/showcase-mahasiswa.webp') }}">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon-32x32.png') }}" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block" rel="stylesheet">
    <style>
        .material-symbols-outlined { user-select: none; vertical-align: middle; font-variation-settings: 'FILL' 0, 'wght' 500, 'GRAD' 0, 'opsz' 24; }
        .icon-sm { font-size: 16px; } .icon-md { font-size: 20px; } .icon-lg { font-size: 24px; }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        try { if (localStorage.getItem('lbta-theme') === 'light') document.documentElement.classList.remove('dark'); } catch (e) {}
    </script>
    <link rel="stylesheet" href="{{ asset('css/global.css') }}?v={{ @filemtime(public_path('css/global.css')) }}">
</head>
<body class="landing bg-bg-base text-text-primary font-sans antialiased">
    @yield('content')
    @yield('scripts')
</body>
</html>