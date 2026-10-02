@extends('layouts.public')

@section('title', 'Beranda')

@section('content')
<a href="#konten" class="landing-skip">Lewati navigasi</a>

<header class="landing-header">
    <div class="landing-container flex items-center justify-between gap-4 py-3">
        <a href="{{ route('landing') }}" class="flex items-center gap-2.5 min-w-0 font-heading font-extrabold tracking-tight text-text-primary" aria-label="{{ $appName }} — Beranda">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-light text-brand p-2">@include('partials.logo-mark')</span>
            <span class="truncate text-sm sm:text-base">{{ $appName }}</span>
        </a>
        <nav aria-label="Navigasi utama" class="hidden lg:flex items-center gap-7 text-sm font-medium text-text-secondary">
            <a href="#fitur" class="hover:text-text-primary transition-colors">Fitur</a>
            <a href="#alur" class="hover:text-text-primary transition-colors">Alur</a>
            <a href="#untuk-siapa" class="hover:text-text-primary transition-colors">Untuk siapa</a>
            <a href="#faq" class="hover:text-text-primary transition-colors">Pertanyaan umum</a>
        </nav>
        <div class="flex items-center gap-2 shrink-0">
            <button type="button" data-theme-toggle class="landing-icon-button" aria-label="Ganti mode gelap atau terang" title="Mode gelap/terang">
                <span data-icon-dark class="material-symbols-outlined icon-md">dark_mode</span>
                <span data-icon-light class="material-symbols-outlined icon-md hidden">light_mode</span>
            </button>
            @auth
                <a href="{{ route('dashboard') }}" class="landing-button landing-button-primary hidden sm:inline-flex">Ke Dashboard <span aria-hidden="true">↗</span></a>
            @else
                <a href="{{ route('login') }}" class="hidden sm:inline-flex landing-button landing-button-quiet">Masuk</a>
                <a href="{{ route('register') }}" class="landing-button landing-button-primary hidden sm:inline-flex">Daftar <span aria-hidden="true">↗</span></a>
            @endauth
            <button type="button" id="landing-menu-toggle" class="landing-icon-button lg:hidden" aria-label="Buka navigasi" aria-controls="landing-mobile-nav" aria-expanded="false">
                <span class="material-symbols-outlined icon-md" aria-hidden="true">menu</span>
            </button>
        </div>
    </div>
    <nav id="landing-mobile-nav" aria-label="Navigasi seluler" class="landing-mobile-nav hidden lg:hidden" hidden>
        <div class="landing-container flex flex-col gap-1 pb-4 text-sm font-medium">
            <a href="#fitur">Fitur</a><a href="#alur">Alur</a><a href="#untuk-siapa">Untuk siapa</a><a href="#faq">Pertanyaan umum</a>
            @auth
                <a href="{{ route('dashboard') }}" class="sm:hidden text-brand">Ke Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="sm:hidden">Masuk</a><a href="{{ route('register') }}" class="sm:hidden text-brand">Daftar</a>
            @endauth
        </div>
    </nav>
</header>

<main id="konten">
    <section class="landing-hero landing-container grid lg:grid-cols-[1fr_0.94fr] gap-12 lg:gap-16 items-center" aria-labelledby="hero-title">
        <div class="max-w-2xl">
            <div class="landing-eyebrow mb-6"><span class="landing-eyebrow-dot"></span><span>Bimbingan yang tertata.<br>Kemajuan yang terlihat.</span></div>
            <h1 id="hero-title" class="landing-display">Campus Logbook Management</h1>
            <p class="mt-6 text-base sm:text-lg leading-relaxed text-text-secondary max-w-xl">Dari entri logbook pertama sampai sidang terakhir, mahasiswa dan dosen bisa mencatat progres, memberi umpan balik, serta menuntaskan revisi dalam satu tempat.</p>
            <div class="mt-8 flex flex-wrap items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="landing-button landing-button-primary landing-button-large">Ke Dashboard <span aria-hidden="true">↗</span></a>
                @else
                    <a href="{{ route('register') }}" class="landing-button landing-button-primary landing-button-large">Buat akun gratis <span aria-hidden="true">↗</span></a>
                    <a href="{{ route('login') }}" class="landing-button landing-button-outline landing-button-large">Sudah punya akun? Masuk</a>
                @endauth
            </div>
            <p class="mt-7 text-xs leading-relaxed text-text-secondary">Untuk mahasiswa, dosen pembimbing, penguji, dan pengelola program.</p>
        </div>

        <div class="landing-journey" aria-label="Contoh alur bimbingan dari entri hingga selesai">
            <div class="landing-journey-top flex items-center justify-between gap-2">
                <span class="flex items-center gap-2 font-semibold"><span class="material-symbols-outlined icon-md text-brand">auto_stories</span> Perjalanan bimbingan</span>
                <span class="font-mono text-xs text-text-secondary">TA / KP</span>
            </div>
            <div class="landing-journey-body">
                <div class="landing-track">
                    <div class="landing-step"><span class="landing-step-icon text-accent-blue"><span class="material-symbols-outlined icon-md">edit_note</span></span><div><div class="flex items-center gap-2 flex-wrap"><strong>Entri bimbingan</strong><span class="landing-step-tag">Mahasiswa</span></div><p>Catat progres dan lampirkan dokumen.</p></div></div>
                    <div class="landing-step"><span class="landing-step-icon text-accent-orange"><span class="material-symbols-outlined icon-md">rate_review</span></span><div><div class="flex items-center gap-2 flex-wrap"><strong>Umpan balik dosen</strong><span class="landing-step-tag">Dosen</span></div><p>Tinjau berkas, beri komentar, dan arahkan langkah berikutnya.</p></div></div>
                    <div class="landing-step"><span class="landing-step-icon text-accent-purple"><span class="material-symbols-outlined icon-md">sync</span></span><div><div class="flex items-center gap-2 flex-wrap"><strong>Revisi terpantau</strong><span class="landing-step-tag">Kolaborasi</span></div><p>Perbaiki pekerjaan tanpa kehilangan riwayat.</p></div></div>
                    <div class="landing-step"><span class="landing-step-icon text-accent-teal"><span class="material-symbols-outlined icon-md">verified</span></span><div><div class="flex items-center gap-2 flex-wrap"><strong>Siap melangkah</strong><span class="landing-step-tag">Seminar &amp; sidang</span></div><p>Seluruh perjalanan tetap tercatat hingga selesai.</p></div></div>
                </div>
            </div>
            <div class="landing-journey-bottom"><span class="material-symbols-outlined icon-sm">history</span> Satu alur. Setiap langkah punya jejaknya.</div>
        </div>
    </section>

    <div class="landing-container"><div class="landing-divider"></div></div>

    <section class="landing-section landing-container" aria-labelledby="masalah-title">
        <div class="grid md:grid-cols-[0.78fr_1fr] gap-8 md:gap-16 items-start">
            <div><span class="landing-section-label">KENAPA PERLU SATU TEMPAT?</span><h2 id="masalah-title" class="landing-heading mt-4">Bimbingan tidak harus tercecer di banyak tempat.</h2></div>
            <div class="space-y-5 text-text-secondary leading-relaxed"><p>Catatan pertemuan ada di buku. Revisi ada di percakapan. Berkas terbaru ada di folder yang berbeda. Ketika waktunya meninjau progres, semua orang harus menyusunnya lagi dari awal.</p><p class="text-text-primary font-semibold">Di sini, entri, dokumen, umpan balik, dan tahapan berikutnya hadir dalam satu alur yang bisa diikuti bersama.</p></div>
        </div>
    </section>

    <section id="fitur" class="landing-section landing-section-muted" aria-labelledby="fitur-title">
        <div class="landing-container">
            <span class="landing-section-label">ALAT UNTUK SETIAP LANGKAH</span>
            <div class="flex flex-wrap justify-between items-end gap-4 mt-4 mb-9"><h2 id="fitur-title" class="landing-heading max-w-2xl">Semua yang dibutuhkan, tanpa memutus alur.</h2><a href="#alur" class="landing-text-link">Lihat alur bimbingan <span aria-hidden="true">→</span></a></div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ([
                    ['edit_document', 'Logbook & revisi', 'Catat setiap sesi, ajukan perbaikan, dan ikuti status review sampai disetujui.', 'blue'],
                    ['picture_as_pdf', 'Review dokumen PDF', 'Baca dokumen bersama, tambahkan anotasi dan komentar langsung pada bagian yang relevan.', 'orange'],
                    ['space_dashboard', 'Dashboard sesuai peran', 'Mahasiswa, dosen, dan admin melihat pekerjaan serta informasi yang mereka butuhkan.', 'teal'],
                    ['folder_open', 'Workspace berkas', 'Simpan dokumen pendukung dan kelola akses berkas di ruang kerja yang tepat.', 'purple'],
                    ['forum', 'Komunikasi terhubung', 'Chat realtime, pengumuman, notifikasi email, dan pengingat membantu semua pihak tetap selaras.', 'blue'],
                    ['event_available', 'Seminar & sidang', 'Pantau tahapan, kelola bahan seminar, dan teruskan perjalanan hingga finalisasi.', 'orange'],
                ] as [$icon, $title, $description, $color])
                    <article class="landing-feature">
                        <span class="landing-feature-icon text-accent-{{ $color }}"><span class="material-symbols-outlined" aria-hidden="true">{{ $icon }}</span></span>
                        <h3 class="font-heading font-bold text-lg mt-6">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-text-secondary">{{ $description }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section id="alur" class="landing-section landing-container" aria-labelledby="alur-title">
        <div class="text-center max-w-2xl mx-auto"><span class="landing-section-label">PERJALANAN FASE</span><h2 id="alur-title" class="landing-heading mt-4">Jelas langkahnya, jelas kemajuannya.</h2><p class="mt-4 text-text-secondary">Setiap program punya tahapan. Bimbingan, revisi, dan peninjauan terdokumentasi sepanjang perjalanan.</p></div>
        <ol class="landing-phases mt-12">
            <li><span class="landing-phase-number">01</span><h3>Mulai &amp; rencanakan</h3><p>Tentukan program, pembimbing, dan arah pengerjaan.</p></li>
            <li><span class="landing-phase-number">02</span><h3>Catat &amp; diskusikan</h3><p>Isi logbook, bagikan berkas, dan terima umpan balik.</p></li>
            <li><span class="landing-phase-number">03</span><h3>Perbaiki &amp; tinjau</h3><p>Kerjakan revisi dengan riwayat perubahan yang jelas.</p></li>
            <li><span class="landing-phase-number">04</span><h3>Seminar &amp; selesaikan</h3><p>Siapkan seminar, sidang, hingga finalisasi.</p></li>
        </ol>
    </section>

    <section id="untuk-siapa" class="landing-section landing-section-muted" aria-labelledby="peran-title">
        <div class="landing-container">
            <span class="landing-section-label">DIBUAT UNTUK BEKERJA BERSAMA</span><h2 id="peran-title" class="landing-heading mt-4 mb-9">Satu ruang kerja. Sudut pandang yang tepat.</h2>
            <div class="grid md:grid-cols-3 gap-4">
                <article class="landing-role"><span class="material-symbols-outlined text-accent-blue text-3xl" aria-hidden="true">school</span><h3>Mahasiswa</h3><p>Catat bimbingan, kirim revisi, lihat umpan balik, dan ketahui apa yang perlu dikerjakan berikutnya.</p></article>
                <article class="landing-role"><span class="material-symbols-outlined text-accent-orange text-3xl" aria-hidden="true">co_present</span><h3>Dosen</h3><p>Tinjau progres mahasiswa, beri komentar pada dokumen, dan kelola antrean review tanpa berpindah-pindah.</p></article>
                <article class="landing-role"><span class="material-symbols-outlined text-accent-purple text-3xl" aria-hidden="true">account_tree</span><h3>Pengelola</h3><p>Atur pengguna, pembimbing, tahapan akademik, dan kebutuhan institusi dari satu sistem.</p></article>
            </div>
        </div>
    </section>

    <section class="landing-section landing-container" aria-labelledby="tampilan-title">
        <div class="flex flex-wrap justify-between items-end gap-4 mb-9"><div><span class="landing-section-label">LIHAT RUANG KERJANYA</span><h2 id="tampilan-title" class="landing-heading mt-4">Dibuat untuk pekerjaan nyata.</h2></div><p class="text-sm text-text-secondary max-w-sm">Tampilan dashboard mahasiswa dan dosen dalam aplikasi.</p></div>
        <div class="grid md:grid-cols-2 gap-5">
            <figure class="landing-screenshot"><img src="{{ asset('images/readme-dashboard-mahasiswa.jpeg') }}" alt="Tampilan dashboard mahasiswa dengan ringkasan progres bimbingan" width="1280" height="952" loading="lazy"><figcaption><span class="material-symbols-outlined icon-sm text-accent-blue" aria-hidden="true">school</span> Dashboard mahasiswa</figcaption></figure>
            <figure class="landing-screenshot"><img src="{{ asset('images/readme-dashboard-dosen.jpeg') }}" alt="Tampilan dashboard dosen dengan ringkasan mahasiswa dan aktivitas bimbingan" width="1280" height="927" loading="lazy"><figcaption><span class="material-symbols-outlined icon-sm text-accent-orange" aria-hidden="true">co_present</span> Dashboard dosen</figcaption></figure>
        </div>
    </section>

    <section class="landing-section landing-section-muted" aria-labelledby="mode-title">
        <div class="landing-container grid md:grid-cols-[0.8fr_1fr] gap-10 md:gap-20 items-start"><div><span class="landing-section-label">FLEKSIBEL UNTUK KAMPUS</span><h2 id="mode-title" class="landing-heading mt-4">Mulai sendiri.<br>Kelola bersama.</h2><p class="mt-4 text-text-secondary leading-relaxed">Gunakan untuk bimbingan pribadi atau sebagai ruang kerja bersama di institusi. Alurnya tetap mengikuti kebutuhan tiap pengguna.</p></div><div class="space-y-4"><div class="landing-mode"><span class="landing-mode-icon text-accent-blue"><span class="material-symbols-outlined" aria-hidden="true">person</span></span><div><h3>Personal</h3><p>Dosen mengelola mahasiswa dan data bimbingannya sendiri.</p></div></div><div class="landing-mode"><span class="landing-mode-icon text-accent-teal"><span class="material-symbols-outlined" aria-hidden="true">domain</span></span><div><h3>Institusi</h3><p>Tim akademik bekerja bersama dengan pengaturan akses dan ruang penyimpanan institusi.</p></div></div></div></div>
    </section>

    <section id="faq" class="landing-section landing-container" aria-labelledby="faq-title">
        <div class="grid md:grid-cols-[0.7fr_1fr] gap-8 md:gap-20"><div><span class="landing-section-label">PERTANYAAN UMUM</span><h2 id="faq-title" class="landing-heading mt-4">Sebelum mulai.</h2></div><div class="landing-faq">
            <details><summary>Siapa yang bisa menggunakan aplikasi ini?<span class="material-symbols-outlined icon-md" aria-hidden="true">add</span></summary><p>Mahasiswa, dosen pembimbing atau penguji, serta pengelola akademik. Tampilan dan akses disesuaikan dengan peran masing-masing.</p></details>
            <details><summary>Apakah bisa digunakan untuk Tugas Akhir dan Kerja Praktik?<span class="material-symbols-outlined icon-md" aria-hidden="true">add</span></summary><p>Ya. Aplikasi mendukung alur bimbingan Tugas Akhir maupun Kerja Praktik, termasuk pencatatan progres dan peninjauan dokumen.</p></details>
            <details><summary>Apakah harus bergabung ke institusi dulu?<span class="material-symbols-outlined icon-md" aria-hidden="true">add</span></summary><p>Tidak selalu. Dosen dapat menggunakan ruang kerja personal; pengguna institusi dapat bekerja bersama sesuai pengaturan akses institusinya.</p></details>
            <details><summary>Bagaimana memulai?<span class="material-symbols-outlined icon-md" aria-hidden="true">add</span></summary><p>Buat akun, lengkapi data yang diminta, lalu ikuti alur sesuai peran Anda. Jika sudah memiliki akun, langsung masuk ke Dashboard.</p></details>
        </div></div>
    </section>

    <section class="landing-container pb-20 sm:pb-28" aria-labelledby="cta-title"><div class="landing-final-cta"><div class="relative z-10"><span class="landing-section-label">MULAI DARI SATU ENTRI</span><h2 id="cta-title" class="landing-heading mt-4 max-w-2xl">Beri setiap langkah bimbingan tempat yang semestinya.</h2><p class="mt-4 text-text-secondary max-w-xl">Catat yang sudah dikerjakan, lihat yang perlu diperbaiki, dan lanjutkan bersama.</p><div class="mt-7 flex flex-wrap gap-3">@auth<a href="{{ route('dashboard') }}" class="landing-button landing-button-primary landing-button-large">Ke Dashboard <span aria-hidden="true">↗</span></a>@else<a href="{{ route('register') }}" class="landing-button landing-button-primary landing-button-large">Buat akun gratis <span aria-hidden="true">↗</span></a><a href="{{ route('login') }}" class="landing-button landing-button-outline landing-button-large">Masuk</a>@endauth</div></div></div></section>
</main>

<footer class="landing-footer"><div class="landing-container py-9 flex flex-col md:flex-row md:items-center justify-between gap-6"><div class="text-sm"><div class="font-heading font-bold text-text-primary">{{ $appName }}</div><p class="text-text-secondary mt-1">{{ $institutionName && $institutionName !== 'Perguruan Tinggi' ? $institutionName : 'Ruang kerja bimbingan akademik' }} · v{{ $version }}</p></div><nav aria-label="Tautan lain" class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-text-secondary"><a href="{{ route('landing') }}" class="hover:text-text-primary">Beranda</a><a href="https://github.com/relooplab/campus-logbook-management" target="_blank" rel="noopener noreferrer" class="hover:text-text-primary">GitHub</a><a href="https://reloop.notion.site/3b1155a221e880829514df5d0a8dcfd6" target="_blank" rel="noopener noreferrer" class="hover:text-text-primary">Kirim Masukan</a>@if($adminContactEmail)<a href="mailto:{{ $adminContactEmail }}" class="hover:text-text-primary">Hubungi admin</a>@endif</nav></div></footer>
@endsection

@section('scripts')
<script>
    (function () {
        var root = document.documentElement;
        var themeButton = document.querySelector('[data-theme-toggle]');
        function syncTheme() {
            var dark = root.classList.contains('dark');
            document.querySelector('[data-icon-dark]').classList.toggle('hidden', !dark);
            document.querySelector('[data-icon-light]').classList.toggle('hidden', dark);
        }
        syncTheme();
        themeButton.addEventListener('click', function () {
            root.classList.toggle('dark');
            try { localStorage.setItem('lbta-theme', root.classList.contains('dark') ? 'dark' : 'light'); } catch (e) {}
            syncTheme();
        });

        var menuButton = document.getElementById('landing-menu-toggle');
        var menu = document.getElementById('landing-mobile-nav');
        function closeMenu() {
            menu.hidden = true;
            menu.classList.add('hidden');
            menuButton.setAttribute('aria-expanded', 'false');
            menuButton.setAttribute('aria-label', 'Buka navigasi');
        }
        menuButton.addEventListener('click', function () {
            var opening = menuButton.getAttribute('aria-expanded') !== 'true';
            menu.hidden = !opening;
            menu.classList.toggle('hidden', !opening);
            menuButton.setAttribute('aria-expanded', String(opening));
            menuButton.setAttribute('aria-label', opening ? 'Tutup navigasi' : 'Buka navigasi');
        });
        menu.querySelectorAll('a').forEach(function (link) { link.addEventListener('click', closeMenu); });
        document.addEventListener('keydown', function (event) { if (event.key === 'Escape') closeMenu(); });
    })();
</script>
@endsection