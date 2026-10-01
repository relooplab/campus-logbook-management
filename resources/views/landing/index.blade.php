@extends('layouts.public')

@section('content')
<a href="#konten" class="landing-skip">Lewati navigasi</a>

{{-- ============================ NAVBAR ============================ --}}
<header class="landing-header">
    <div class="landing-container flex items-center justify-between gap-6 py-3.5">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5 min-w-0" aria-label="{{ $appName }} — Beranda">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-light text-brand p-2">@include('partials.logo-mark')</span>
            <span class="min-w-0">
                <span class="block font-heading font-extrabold leading-tight tracking-tight text-text-primary">Campus Logbook</span>
                <span class="block text-[11px] leading-tight text-text-secondary">Management</span>
            </span>
        </a>

        <nav aria-label="Navigasi utama" class="hidden md:flex items-center gap-7 text-sm font-medium text-text-secondary">
            <a href="#beranda" class="hover:text-text-primary transition-colors">Beranda</a>
            <a href="#fitur" class="hover:text-text-primary transition-colors">Fitur</a>
            <a href="#untuk-siapa" class="hover:text-text-primary transition-colors">Untuk Siapa</a>
        </nav>

        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('login') }}" class="hidden sm:inline-flex landing-button landing-button-outline">Masuk</a>
            <button type="button" id="landing-menu-toggle" class="landing-icon-button md:hidden" aria-label="Buka navigasi" aria-controls="landing-mobile-nav" aria-expanded="false">
                <span class="material-symbols-outlined icon-md" aria-hidden="true">menu</span>
            </button>
        </div>
    </div>

    <nav id="landing-mobile-nav" aria-label="Navigasi seluler" class="landing-mobile-nav md:hidden" hidden>
        <div class="landing-container flex flex-col py-3 text-sm font-medium">
            <a href="#beranda">Beranda</a>
            <a href="#fitur">Fitur</a>
            <a href="#untuk-siapa">Untuk Siapa</a>
            <a href="{{ route('login') }}" class="mt-1 border-t border-border pt-3 text-brand sm:hidden">Masuk</a>
        </div>
    </nav>
</header>

<main id="konten">
    {{-- ============================ HERO ============================ --}}
    <section id="beranda" class="landing-hero" aria-labelledby="hero-title">
        <div class="landing-container grid items-center gap-12 lg:grid-cols-[45fr_55fr] lg:gap-14">
            <div>
                <p class="landing-eyebrow">BIMBINGAN AKADEMIK DALAM SATU ALUR</p>
                <h1 id="hero-title" class="landing-display">Kelola bimbingan TA &amp; KP dari logbook hingga finalisasi.</h1>
                <p class="landing-lead">Campus Logbook menghubungkan mahasiswa, pembimbing, dan penguji dalam proses bimbingan yang terstruktur, terdokumentasi, dan mudah dipantau.</p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <a href="{{ route('login') }}" class="landing-button landing-button-primary landing-button-lg">Masuk ke Sistem</a>
                    <a href="{{ route('register') }}" class="landing-button landing-button-outline landing-button-lg">Daftar sebagai Mahasiswa</a>
                </div>
            </div>

            {{-- Visual produk: cuplikan dashboard Campus Logbook sungguhan.
                 Untuk memakai artwork gabungan laptop + smartphone dari tim desain,
                 simpan sebagai public/images/landing/campus-logbook-showcase.webp
                 lalu ganti isi blok ini dengan satu elemen <img>. --}}
            <div class="landing-showcase">
                <div class="landing-shot landing-shot-main">
                    <img src="{{ asset('images/landing/showcase-mahasiswa.webp') }}" alt="Dashboard Campus Logbook pada tampilan desktop dengan data demonstrasi" width="1400" height="1042" fetchpriority="high" decoding="async">
                </div>
            </div>
        </div>
    </section>

    {{-- ============================ EMPAT FITUR INTI ============================ --}}
    <section id="fitur" class="landing-section" aria-labelledby="fitur-title">
        <div class="landing-container">
            <h2 id="fitur-title" class="sr-only">Fitur utama Campus Logbook</h2>
            <ul class="landing-features">
                <li class="landing-feature">
                    <span class="landing-feature-icon" style="color: rgb(var(--brand))"><span class="material-symbols-outlined" aria-hidden="true">groups</span></span>
                    <h3>Bimbingan Terstruktur</h3>
                    <p>Catat sesi bimbingan, progres, dan tindak lanjut dalam satu riwayat.</p>
                </li>
                <li class="landing-feature">
                    <span class="landing-feature-icon" style="color: rgb(var(--accent-blue))"><span class="material-symbols-outlined" aria-hidden="true">rate_review</span></span>
                    <h3>Review &amp; Revisi</h3>
                    <p>Review logbook, beri anotasi PDF, dan kelola revisi secara terstruktur.</p>
                </li>
                <li class="landing-feature">
                    <span class="landing-feature-icon" style="color: rgb(var(--accent-teal))"><span class="material-symbols-outlined" aria-hidden="true">trending_up</span></span>
                    <h3>Pantau Progres</h3>
                    <p>Ikuti fase TA/KP, progres bimbingan, seminar, hingga finalisasi.</p>
                </li>
                <li class="landing-feature">
                    <span class="landing-feature-icon" style="color: rgb(var(--accent-orange))"><span class="material-symbols-outlined" aria-hidden="true">folder_open</span></span>
                    <h3>Workspace Terpusat</h3>
                    <p>Simpan dan kelola dokumen bimbingan dalam workspace yang terkontrol.</p>
                </li>
            </ul>
        </div>
    </section>
    {{-- ============================ UNTUK SIAPA ============================ --}}
    <section id="untuk-siapa" class="landing-section landing-section-muted" aria-labelledby="siapa-title">
        <div class="landing-container">
            <h2 id="siapa-title" class="landing-heading">Dibangun untuk proses bimbingan akademik</h2>
            <ul class="landing-audience">
                <li>
                    <span class="material-symbols-outlined" aria-hidden="true">school</span>
                    <h3>Mahasiswa</h3>
                    <p>Dokumentasikan progres, kirim revisi, dan pantau perjalanan TA/KP.</p>
                </li>
                <li>
                    <span class="material-symbols-outlined" aria-hidden="true">co_present</span>
                    <h3>Dosen</h3>
                    <p>Review bimbingan, beri feedback, dan pantau mahasiswa dalam satu tempat.</p>
                </li>
                <li>
                    <span class="material-symbols-outlined" aria-hidden="true">domain</span>
                    <h3>Institusi</h3>
                    <p>Kelola proses bimbingan dan struktur akademik secara lebih terpusat.</p>
                </li>
            </ul>
        </div>
    </section>

    {{-- ============================ CTA AKHIR ============================ --}}
    <section class="landing-container landing-cta-wrap" aria-labelledby="cta-title">
        <div class="landing-cta">
            <h2 id="cta-title">Mulai kelola bimbingan dengan lebih terstruktur.</h2>
            <p>Masuk ke Campus Logbook atau daftar sebagai mahasiswa untuk memulai.</p>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-center">
                <a href="{{ route('login') }}" class="landing-button landing-button-primary landing-button-lg">Masuk ke Sistem</a>
                <a href="{{ route('register') }}" class="landing-button landing-button-outline landing-button-lg">Daftar Mahasiswa</a>
            </div>
        </div>
    </section>
</main>

{{-- ============================ FOOTER ============================ --}}
<footer class="landing-footer">
    <div class="landing-container flex flex-col gap-4 py-7 sm:flex-row sm:items-center sm:justify-between">
        <div class="text-sm">
            <p class="font-heading font-bold text-text-primary">{{ $appName }}</p>
            <p class="text-text-secondary mt-0.5">Academic supervision management platform</p>
        </div>
        <nav aria-label="Tautan lain" class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-text-secondary">
            <a href="https://github.com/relooplab/campus-logbook-management/blob/main/docs/USER-GUIDE.md" target="_blank" rel="noopener noreferrer" class="hover:text-text-primary">Dokumentasi</a>
            <a href="https://github.com/relooplab/campus-logbook-management" target="_blank" rel="noopener noreferrer" class="hover:text-text-primary">GitHub</a>
            <a href="{{ route('login') }}" class="hover:text-text-primary">Masuk</a>
        </nav>
    </div>
    <div class="landing-container pb-7">
        <p class="border-t border-border pt-5 text-xs text-text-secondary">&copy; {{ date('Y') }} {{ $appName }}.</p>
    </div>
</footer>
@endsection

@section('scripts')
<script>
    // Menu seluler memakai atribut hidden + aria-expanded supaya tetap
    // dapat diakses keyboard dan terbaca screen reader.
    (function () {
        var button = document.getElementById('landing-menu-toggle');
        var menu = document.getElementById('landing-mobile-nav');
        if (!button || !menu) return;

        function setOpen(open) {
            menu.hidden = !open;
            button.setAttribute('aria-expanded', String(open));
            button.setAttribute('aria-label', open ? 'Tutup navigasi' : 'Buka navigasi');
            button.querySelector('.material-symbols-outlined').textContent = open ? 'close' : 'menu';
        }

        button.addEventListener('click', function () {
            setOpen(button.getAttribute('aria-expanded') !== 'true');
        });
        menu.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () { setOpen(false); });
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && button.getAttribute('aria-expanded') === 'true') {
                setOpen(false);
                button.focus();
            }
        });
    })();
</script>
@endsection
