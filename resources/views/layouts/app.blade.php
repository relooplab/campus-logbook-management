@php
    $user = auth()->user();
    $active = fn ($name) => request()->routeIs($name)
        ? 'bg-bg-hover text-text-primary font-semibold'
        : 'text-text-secondary hover:bg-bg-hover hover:text-text-primary';
    $navLink = 'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors';
@endphp
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Thesis Logbook Management') · Thesis Logbook Management</title>
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
                        bg: {
                            base: 'rgb(var(--bg-base) / <alpha-value>)',
                            surface: 'rgb(var(--bg-surface) / <alpha-value>)',
                            panel: 'rgb(var(--bg-panel) / <alpha-value>)',
                            hover: 'rgb(var(--bg-hover) / <alpha-value>)',
                        },
                        border: { DEFAULT: 'rgb(var(--border) / <alpha-value>)' },
                        text: {
                            primary: 'rgb(var(--text-primary) / <alpha-value>)',
                            secondary: 'rgb(var(--text-secondary) / <alpha-value>)',
                        },
                        accent: {
                            blue: 'rgb(var(--accent-blue) / <alpha-value>)',
                            orange: 'rgb(var(--accent-orange) / <alpha-value>)',
                            teal: 'rgb(var(--accent-teal) / <alpha-value>)',
                            purple: 'rgb(var(--accent-purple) / <alpha-value>)',
                        },
                        status: {
                            success: 'rgb(var(--status-success) / <alpha-value>)',
                            danger: 'rgb(var(--status-danger) / <alpha-value>)',
                            info: 'rgb(var(--status-info) / <alpha-value>)',
                            pending: 'rgb(var(--status-pending) / <alpha-value>)',
                        },
                        'card-inverse': 'rgb(var(--card-inverse) / <alpha-value>)',
                    },
                    borderRadius: { card: '20px' },
                    spacing: { card: '24px' },
                },
            },
        };
        // Default dark; persist toggle via localStorage.
        (function () {
            var saved = localStorage.getItem('lbta-theme');
            if (saved === 'light') document.documentElement.classList.remove('dark');
        })();
    </script>
    <link rel="stylesheet" href="{{ asset('css/global.css') }}">
    <style>
        .progress-bar { transition: width .4s ease; }
    </style>
    @yield('head')
</head>
<body class="bg-bg-base text-text-primary min-h-screen font-sans antialiased">

<div class="flex min-h-screen">

    {{-- Overlay (mobile only, shown while sidebar drawer is open) --}}
    <div id="sidebar-overlay" class="hidden fixed inset-0 bg-black/50 z-30 md:hidden"></div>

    {{-- ===================== SIDEBAR (fixed kiri) ===================== --}}
    <aside id="sidebar" class="fixed left-0 top-0 h-screen w-60 bg-bg-base border-r border-border flex flex-col z-40 -translate-x-full md:translate-x-0 transition-transform duration-200 ease-in-out">
        <div class="px-6 py-6 flex items-center gap-2.5">
            <span class="w-9 h-9 rounded-xl bg-accent-blue/15 text-accent-blue flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
            </span>
            <div class="min-w-0 flex-1">
                <div class="font-heading font-extrabold text-lg text-text-primary leading-tight truncate">Thesis Logbook</div>
                <div class="text-[10px] uppercase tracking-widest text-text-secondary -mt-0.5">Management</div>
            </div>
            <button type="button" id="sidebar-close-btn" title="Tutup menu" class="md:hidden p-1.5 rounded-lg text-text-secondary hover:bg-bg-hover hover:text-text-primary">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        @auth
            @if (\App\Support\Feature::isInstitution())
                <div class="px-6 pb-2"><span class="text-[10px] px-2 py-0.5 rounded-full bg-accent-purple/15 text-accent-purple">🏛️ Institusi</span></div>
            @else
                <div class="px-6 pb-2"><span class="text-[10px] px-2 py-0.5 rounded-full bg-accent-teal/15 text-accent-teal">🌱 Individual</span></div>
            @endif
        @endauth

        <nav class="flex-1 px-3 space-y-1 overflow-y-auto mt-1">
            <a href="{{ route('dashboard') }}" class="{{ $navLink }} {{ $active('dashboard') }}">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1V10"/></svg>
                Dashboard
            </a>
            <a href="{{ route('chat.index') }}" class="{{ $navLink }} {{ $active('chat.*') }}">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Chat
            </a>
            <a href="{{ route('announcements.index') }}" class="{{ $navLink }} {{ $active('announcements.*') }}">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                Pengumuman
            </a>

            @if ($user->isMahasiswa())
                <a href="{{ route('logbook.index') }}" class="{{ $navLink }} {{ $active('logbook.index') }}">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M9 8h6M7 4h10a2 2 0 012 2v14l-3-2-3 2-3-2-3 2V6a2 2 0 012-2z"/></svg>
                    Logbook
                </a>
                <a href="{{ route('logbook.create') }}" class="{{ $navLink }} {{ $active('logbook.create') }}">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Tambah Logbook
                </a>
                <a href="{{ route('logbook.create-revisi') }}" class="{{ $navLink }} {{ $active('logbook.create-revisi') }}">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Entri Revisi
                </a>
                @if ($user->mahasiswaTa)
                    <a href="{{ route('workspace.index', $user->mahasiswaTa) }}" class="{{ $navLink }} {{ $active('workspace.*') }}">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        Workspace
                    </a>
                @endif
            @elseif ($user->isDosen())
                <a href="{{ route('logbook.index') }}" class="{{ $navLink }} {{ $active('logbook.index') }}">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586l-9 9V21H8v-5.414l-5-5V4z"/></svg>
                    Antrean Review
                </a>
                <a href="{{ route('quick-review.index') }}" class="{{ $navLink }} {{ $active('quick-review.*') }}">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Quick Review
                </a>
                <a href="{{ route('dosen-sidang.index') }}" class="{{ $navLink }} {{ $active('dosen-sidang.*') }}">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    Catat Sidang
                </a>
                <a href="{{ route('approval.index') }}" class="{{ $navLink }} {{ $active('approval.*') }}">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Persetujuan
                </a>
            @endif

            @if ($user->isAdmin())
                <div class="px-3 pt-4 pb-1 text-[10px] uppercase tracking-widest text-text-secondary">Administrasi</div>
                <a href="{{ route('admin.users') }}" class="{{ $navLink }} {{ $active('admin.users') }}">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Pengguna
                </a>
                <a href="{{ route('admin.tas') }}" class="{{ $navLink }} {{ $active('admin.tas') }}">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    Data TA
                </a>
                <a href="{{ route('admin.entries') }}" class="{{ $navLink }} {{ $active('admin.entries') }}">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    Review Massal
                </a>
                <a href="{{ route('admin.sidangs') }}" class="{{ $navLink }} {{ $active('admin.sidangs') }}">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6l9-4 9 4v6a9 9 0 01-18 0V6zm9 4v6m-3-3h6"/></svg>
                    Sidang
                </a>
                <a href="{{ route('admin.institution') }}" class="{{ $navLink }} {{ $active('admin.institution') }}">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    Institusi
                </a>
            @endif
        </nav>

        <div class="p-4 border-t border-border space-y-3">
            <a href="{{ config('app.jadwal_url') }}" target="_blank" rel="noopener"
               class="flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">
                📅 Jadwal Bimbingan
            </a>
            @auth
            <div class="relative" id="profile-menu-wrap">
                <div id="profile-menu-dropdown" class="hidden absolute left-0 bottom-full mb-2 w-56 bg-bg-surface rounded-xl shadow-lg border border-border overflow-hidden">
                    <div class="px-4 py-3 border-b border-border">
                        <div class="text-sm font-medium text-text-primary truncate">{{ $user->name }}</div>
                        <div class="text-xs text-text-secondary truncate">{{ $user->email }}</div>
                    </div>
                    <a href="{{ route('profile.index') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-text-secondary hover:bg-bg-hover hover:text-text-primary">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Profil
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-text-secondary hover:bg-bg-hover hover:text-status-danger">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            Keluar
                        </button>
                    </form>
                </div>
                <button type="button" id="profile-menu-btn" title="{{ $user->name }}" class="w-10 h-10 rounded-full bg-accent-purple/20 text-accent-purple flex items-center justify-center text-xs font-bold overflow-hidden hover:ring-2 hover:ring-accent-blue/40 transition">
                    @if ($user->photoUrl())
                        <img src="{{ $user->photoUrl() }}" class="h-full w-full object-cover" alt="Foto profil">
                    @else
                        {{ $user->initials() }}
                    @endif
                </button>
            </div>
            @endauth
        </div>
    </aside>

    {{-- ===================== MAIN CONTENT ===================== --}}
    <div class="flex-1 md:ml-60">
        <header class="sticky top-0 z-30 h-16 bg-bg-base/80 backdrop-blur border-b border-border px-4 md:px-8 flex items-center justify-between">
            <div class="flex items-center gap-3 min-w-0">
                <button type="button" id="sidebar-open-btn" title="Buka menu" class="md:hidden p-2 rounded-xl bg-bg-hover text-text-secondary hover:text-text-primary shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                @yield('header-title', '')
            </div>
            <div class="flex items-center gap-3">
                <div class="relative hidden md:block">
                    <input type="text" id="global-search-input" placeholder="Cari (Cmd+K)…"
                        class="w-56 rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm text-text-primary placeholder:text-text-secondary focus:outline-none focus:ring-2 focus:ring-accent-blue/40">
                    <div id="global-search-results" class="hidden absolute right-0 mt-2 w-80 bg-bg-surface rounded-card shadow-lg border border-border overflow-hidden"></div>
                </div>

                <div class="relative">
                    <button type="button" id="notif-bell" class="relative p-2.5 rounded-xl bg-bg-hover text-text-secondary hover:text-text-primary" title="Notifikasi">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        <span id="notif-badge" class="hidden absolute -top-1 -right-1 h-4 w-4 rounded-full bg-status-danger text-[10px] text-white items-center justify-center"></span>
                    </button>
                    <div id="notif-dropdown" class="hidden absolute right-0 mt-2 w-80 bg-bg-surface rounded-card shadow-lg border border-border overflow-hidden">
                        <div class="flex items-center justify-between px-4 py-2.5 border-b border-border">
                            <span class="text-sm font-semibold text-text-primary">Notifikasi</span>
                            <a href="{{ route('notifications.index') }}" class="text-xs text-accent-blue hover:underline">Lihat semua</a>
                        </div>
                        <div id="notif-list" class="max-h-80 overflow-y-auto text-sm">
                            <div class="px-4 py-3 text-text-secondary">Memuat…</div>
                        </div>
                    </div>
                </div>

                <button type="button" id="theme-toggle" class="p-2.5 rounded-xl bg-bg-hover text-text-secondary hover:text-text-primary" title="Mode gelap/terang">
                    <svg id="icon-dark" class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/>
                    </svg>
                    <svg id="icon-light" class="h-5 w-5 hidden" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
        </header>

        <main class="px-8 py-8">
            @if (session('success'))
                <div class="mb-5 px-4 py-3 rounded-xl bg-status-success/10 text-status-success border border-status-success/20 flex items-start gap-2.5">
                    <span class="mt-0.5">✅</span><span>{{ session('success') }}</span>
                </div>
            @endif
            @if (session('error'))
                <div class="mb-5 px-4 py-3 rounded-xl bg-status-danger/10 text-status-danger border border-status-danger/20 flex items-start gap-2.5">
                    <span class="mt-0.5">⚠️</span><span>{{ session('error') }}</span>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<script src="https://js.pusher.com/7.2/pusher.min.js"></script>
<script>
    // Realtime notifikasi (Reverb via protokol Pusher)
    (function () {
        var userId = {{ auth()->id() ?? 'null' }};
        if (!userId) return;
        var rKey = @json(config('broadcasting.connections.reverb.key')) || 'lbta-key';
        var rHost = @json(config('broadcasting.connections.reverb.host')) || window.location.hostname;
        var rPort = @json(config('broadcasting.connections.reverb.port')) || 443;
        var rScheme = @json(config('broadcasting.connections.reverb.scheme')) || 'http';
        var pusher;
        try {
            pusher = new Pusher(rKey, {
                wsHost: rHost,
                wsPort: rPort,
                wssPort: rPort,
                forceTLS: rScheme === 'https',
                encrypted: rScheme === 'https',
                enabledTransports: ['ws', 'wss'],
                disableStats: true
            });
        } catch (e) { return; }

        function toast(msg) {
            var el = document.createElement('div');
            el.className = 'fixed bottom-4 right-4 z-50 max-w-sm px-4 py-3 rounded-card shadow-lg bg-bg-surface border border-border text-sm text-text-primary';
            el.textContent = msg;
            document.body.appendChild(el);
            setTimeout(function () { el.remove(); }, 5000);
        }
        function bump() {
            var b = document.getElementById('notif-badge');
            b.classList.remove('hidden');
            b.classList.add('flex');
            var n = parseInt(b.textContent || '0', 10) + 1;
            b.textContent = n > 9 ? '9+' : n;
        }

        var ch = pusher.subscribe('user.' + userId);
        ch.bind('entry.status.changed', function (data) { bump(); toast(data.message); });
        ch.bind('pdf.comment.created', function (data) { bump(); toast('Komentar baru pada PDF.'); });
    })();
</script>
<script>
    // Dark mode toggle
    var toggle = document.getElementById('theme-toggle');
    var isDark = function () { return document.documentElement.classList.contains('dark'); };
    function syncIcons() {
        document.getElementById('icon-dark').classList.toggle('hidden', !isDark());
        document.getElementById('icon-light').classList.toggle('hidden', isDark());
    }
    syncIcons();
    toggle.addEventListener('click', function () {
        if (isDark()) document.documentElement.classList.remove('dark');
        else document.documentElement.classList.add('dark');
        localStorage.setItem('lbta-theme', isDark() ? 'dark' : 'light');
        syncIcons();
    });
</script>
<script>
    // ---- Dropdown notifikasi ----
    (function () {
        var bell = document.getElementById('notif-bell');
        var drop = document.getElementById('notif-dropdown');
        var list = document.getElementById('notif-list');
        if (!bell || !drop) return;

        function esc(s) {
            return String(s || '').replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }
        function load() {
            fetch('{{ route("notifications.dropdown") }}', { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var badge = document.getElementById('notif-badge');
                    if (data.unread > 0) {
                        badge.classList.remove('hidden');
                        badge.classList.add('flex');
                        badge.textContent = data.unread > 9 ? '9+' : data.unread;
                    } else {
                        badge.classList.add('hidden');
                    }
                    if (!data.items.length) {
                        list.innerHTML = '<div class="px-4 py-3 text-text-secondary">Tidak ada notifikasi.</div>';
                        return;
                    }
                    list.innerHTML = data.items.map(function (n) {
                        var unread = !n.read_at;
                        return '<a href="' + esc(n.url || '{{ route("notifications.index") }}') + '" class="flex items-start gap-2 px-4 py-2.5 border-b border-border hover:bg-bg-hover ' + (unread ? 'bg-accent-blue/5' : '') + '">' +
                            '<span class="mt-1 h-2 w-2 rounded-full flex-shrink-0 ' + (unread ? 'bg-accent-blue' : 'bg-text-secondary/40') + '"></span>' +
                            '<span class="min-w-0"><span class="block text-xs leading-snug text-text-primary">' + esc(n.message) + '</span>' +
                            '<span class="block text-[10px] text-text-secondary">' + esc(n.created_at) + '</span></span></a>';
                    }).join('') +
                    '<a href="{{ route("notifications.index") }}" class="block px-4 py-2 text-center text-xs text-accent-blue hover:underline">Lihat semua</a>';
                });
        }
        bell.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = !drop.classList.contains('hidden');
            drop.classList.toggle('hidden');
            if (!open) load();
        });
        document.addEventListener('click', function () { drop.classList.add('hidden'); });
        fetch('{{ route("notifications.dropdown") }}', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var badge = document.getElementById('notif-badge');
                if (d.unread > 0) { badge.classList.remove('hidden'); badge.classList.add('flex'); badge.textContent = d.unread > 9 ? '9+' : d.unread; }
            });
    })();

    // ---- Sidebar drawer (mobile) ----
    (function () {
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebar-overlay');
        var openBtn = document.getElementById('sidebar-open-btn');
        var closeBtn = document.getElementById('sidebar-close-btn');
        if (!sidebar || !overlay) return;

        function openSidebar() {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
        }
        function closeSidebar() {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
        }
        if (openBtn) openBtn.addEventListener('click', openSidebar);
        if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
        overlay.addEventListener('click', closeSidebar);
        sidebar.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function () {
                if (window.matchMedia('(max-width: 767px)').matches) closeSidebar();
            });
        });
        window.addEventListener('resize', function () {
            if (window.matchMedia('(min-width: 768px)').matches) overlay.classList.add('hidden');
        });
    })();

    // ---- Profile dropdown ----
    (function () {
        var btn = document.getElementById('profile-menu-btn');
        var drop = document.getElementById('profile-menu-dropdown');
        if (!btn || !drop) return;
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            drop.classList.toggle('hidden');
        });
        document.addEventListener('click', function () { drop.classList.add('hidden'); });
    })();

    // ---- Global search (Cmd+K) ----
    (function () {
        var input = document.getElementById('global-search-input');
        if (!input) return;
        var results = document.getElementById('global-search-results');
        var timer;
        function esc(s) {
            return String(s || '').replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }
        document.addEventListener('keydown', function (e) {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                input.focus();
            }
        });
        input.addEventListener('input', function () {
            clearTimeout(timer);
            var q = input.value.trim();
            if (q.length < 2) { results.classList.add('hidden'); return; }
            timer = setTimeout(function () {
                fetch('{{ route("global-search") }}?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        var html = '';
                        if (d.users.length) {
                            html += '<p class="px-4 py-1 text-[10px] uppercase tracking-wider text-text-secondary">Mahasiswa/Dosen</p>';
                            d.users.forEach(function (u) {
                                html += '<a href="' + esc(u.url) + '" class="flex items-center gap-2 px-4 py-2 hover:bg-bg-hover text-sm"><span class="w-6 h-6 rounded-full bg-accent-purple/15 text-accent-purple flex items-center justify-center text-[10px] font-bold">' + esc((u.name || '?').charAt(0)) + '</span><span class="min-w-0"><span class="block text-text-primary truncate">' + esc(u.name) + '</span><span class="block text-xs text-text-secondary">' + esc(u.identifier || '') + '</span></span></a>';
                            });
                        }
                        if (d.entries.length) {
                            html += '<p class="px-4 py-1 text-[10px] uppercase tracking-wider text-text-secondary">Entri</p>';
                            d.entries.forEach(function (e) {
                                html += '<a href="' + esc(e.url) + '" class="block px-4 py-2 hover:bg-bg-hover text-sm text-text-primary">📄 ' + esc(e.title) + ' <span class="text-xs text-text-secondary">' + esc(e.student || '') + '</span></a>';
                            });
                        }
                        if (d.files.length) {
                            html += '<p class="px-4 py-1 text-[10px] uppercase tracking-wider text-text-secondary">File Workspace</p>';
                            d.files.forEach(function (f) {
                                html += '<a href="' + esc(f.url) + '" class="block px-4 py-2 hover:bg-bg-hover text-sm text-text-primary">📁 ' + esc(f.name) + '</a>';
                            });
                        }
                        if (!html) html = '<div class="px-4 py-3 text-text-secondary">Tidak ada hasil.</div>';
                        results.innerHTML = html;
                        results.classList.remove('hidden');
                    });
            }, 300);
        });
        document.addEventListener('click', function (e) {
            if (!results.contains(e.target) && e.target !== input) results.classList.add('hidden');
        });
    })();
</script>
@yield('scripts')
</body>
</html>
