@extends('layouts.app')

@section('title', 'Dashboard Mahasiswa')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading font-bold text-2xl text-text-primary">Dashboard Mahasiswa</h1>
            <p class="text-sm text-text-secondary mt-0.5">Pantau progres bimbingan {{ $ta?->isKp() ? 'Kerja Praktek' : 'Tugas Akhir' }} Anda</p>
        </div>
        <div class="flex flex-wrap gap-2 w-full sm:w-auto">
            <a href="{{ route('logbook.create') }}" class="flex-1 sm:flex-none px-4 py-2 rounded-xl bg-brand-fill text-white text-sm font-semibold shadow-md hover:bg-brand-fill-hover hover:shadow-lg transition-shadow text-center" title="Tambah entri logbook bimbingan baru (aksi utama)">+ Logbook</a>
            <a href="{{ route('logbook.create-revisi') }}" class="flex-1 sm:flex-none px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border text-center">+ Entri Revisi</a>
            <a href="{{ route('logbook.index') }}" class="flex-1 sm:flex-none px-4 py-2 rounded-xl bg-transparent border border-border text-text-secondary text-sm font-medium hover:bg-bg-hover hover:text-text-primary text-center">Semua Entri</a>
        </div>
    </div>

    {{-- ===== Banner status mahasiswa (aktif/verified) ===== --}}
    @if ($mahasiswaStatus === 'active' && (!$ta || $rejectedProgram))
        <div class="px-4 py-3.5 rounded-card border border-status-pending/30 bg-status-pending/10 flex flex-wrap items-center gap-3">
            <span class="material-symbols-outlined icon-md shrink-0 text-status-pending">person_add</span>
            <div class="flex-1 min-w-0 text-sm">
                <p class="font-semibold text-text-primary">Pilih Dosen untuk Memulai Program</p>
                @if ($rejectedProgram)
                    <p class="text-text-secondary">Permintaan Anda sebelumnya ditolak dosen
                        @if ($ta->alasan_ditolak)
                            dengan alasan: <span class="font-medium text-text-primary">"{{ $ta->alasan_ditolak }}"</span>
                        @endif
                        . Silakan pilih dosen lain.</p>
                @else
                    <p class="text-text-secondary">Pilih dosen pembimbing dan penguji untuk TA/KP Anda.</p>
                @endif
            </div>
            <a href="{{ route('profile.select-dosen') }}" class="px-3 py-1.5 rounded-xl bg-brand text-white text-xs font-medium hover:opacity-90">Pilih Dosen</a>
        </div>
    @elseif ($pendingApproval)
        <div class="px-4 py-3.5 rounded-card border border-status-pending/30 bg-status-pending/10 flex flex-wrap items-center gap-3">
            <span class="material-symbols-outlined icon-md shrink-0 text-status-pending">schedule</span>
            <div class="flex-1 min-w-0 text-sm">
                <p class="font-semibold text-text-primary">Menunggu Persetujuan Dosen</p>
                <p class="text-text-secondary">Permintaan attachment Anda sedang menunggu persetujuan dosen.</p>
            </div>
        </div>
    @endif

    {{-- ===== Aksi Saya ===== --}}
    @if ($ta)
        <div class="card p-6">
            <div class="flex items-center gap-3 mb-4">
                <span class="icon-circle w-10 h-10 bg-brand-light text-brand">
                    <span class="material-symbols-outlined icon-md">today</span>
                </span>
                <div>
                    <h2 class="font-heading font-semibold text-text-primary">Aksi Saya</h2>
                    <p class="text-sm text-text-secondary">Hal yang perlu Anda tindak lanjuti</p>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <a href="{{ route('logbook.index', ['status' => 'draft']) }}" class="flex items-center gap-3 p-4 rounded-xl bg-bg-panel border border-border hover:border-brand/30 transition-colors">
                    <span class="icon-circle w-10 h-10 bg-status-pending/15 text-status-pending">
                        <span class="material-symbols-outlined icon-md">draft</span>
                    </span>
                    <div>
                        <div class="font-heading font-bold text-2xl text-text-primary tabular-nums">{{ $draftCount }}</div>
                        <div class="text-sm text-text-secondary">Draf belum dikirim</div>
                    </div>
                    @if ($draftCount > 0)
                        <span class="ml-auto text-brand text-xs font-medium">Kirim →</span>
                    @endif
                </a>
                <a href="{{ route('logbook.index', ['status' => 'revisi']) }}" class="flex items-center gap-3 p-4 rounded-xl bg-bg-panel border border-border hover:border-brand/30 transition-colors">
                    <span class="icon-circle w-10 h-10 bg-status-danger/15 text-status-danger">
                        <span class="material-symbols-outlined icon-md">edit_note</span>
                    </span>
                    <div>
                        <div class="font-heading font-bold text-2xl text-text-primary tabular-nums">{{ $revisiCount }}</div>
                        <div class="text-sm text-text-secondary">Revisi perlu ditanggapi</div>
                    </div>
                    @if ($revisiCount > 0)
                        <span class="ml-auto text-brand text-xs font-medium">Tanggapi →</span>
                    @endif
                </a>
                <a href="{{ route('logbook.feedback') }}" class="flex items-center gap-3 p-4 rounded-xl bg-bg-panel border border-border hover:border-brand/30 transition-colors">
                    <span class="icon-circle w-10 h-10 bg-brand-light text-brand">
                        <span class="material-symbols-outlined icon-md">checklist</span>
                    </span>
                    <div>
                        <div class="font-heading font-bold text-2xl text-text-primary tabular-nums">{{ $unresolvedActionItems }}</div>
                        <div class="text-sm text-text-secondary">Action items belum selesai</div>
                    </div>
                    @if ($unresolvedActionItems > 0)
                        <span class="ml-auto text-brand text-xs font-medium">Lihat →</span>
                    @endif
                </a>
            </div>
        </div>
    @endif

    {{-- ===== Universitas ===== --}}
    @if ($university)
        <div class="card p-6">
            <div class="flex items-center gap-3 mb-3">
                <span class="icon-circle w-10 h-10 bg-brand-light text-brand">
                    <span class="material-symbols-outlined icon-md">account_balance</span>
                </span>
                <div>
                    <h2 class="font-heading font-semibold text-text-primary">Universitas</h2>
                    <p class="text-sm text-text-secondary">Afiliasi program Anda</p>
                </div>
            </div>
            <p class="font-medium text-text-primary">{{ $university->name }}</p>
            @if ($university->npsn)
                <p class="text-xs text-text-secondary mt-0.5">NPSN: {{ $university->npsn }}</p>
            @endif
        </div>
    @endif

    {{-- ===== Tab program KP / TA ===== --}}
    @include('partials.program-selector', ['ta' => $ta, 'route' => 'dashboard'])

    @if (!$ta)
        <div class="px-4 py-6 rounded-card bg-status-pending/10 text-status-pending border border-status-pending/20 flex items-start gap-2.5">
            <span class="material-symbols-outlined icon-md mt-0.5">info</span><span>Data program Anda (TA/KP) belum diinput oleh admin.</span>
        </div>
    @else
        {{-- ===== Peringatan: judul TA / tempat KP wajib diisi ===== --}}
        @php
            $belumIsi = $ta->isKp() ? blank($ta->tempat_kp) : blank($ta->judul_ta);
        @endphp
        @if ($belumIsi && $ta->status_ta === \App\Models\MahasiswaTa::STATUS_AKTIF)
            <div class="px-4 py-3.5 rounded-card border border-status-danger/30 bg-status-danger/10 flex flex-wrap items-center gap-3">
                <span class="material-symbols-outlined icon-md shrink-0 text-status-danger">error</span>
                <div class="flex-1 min-w-0 text-sm">
                    <p class="font-semibold text-text-primary">Lengkapi {{ $ta->isKp() ? 'Tempat Kerja Praktek' : 'Judul Tugas Akhir' }} Anda</p>
                    <p class="text-text-secondary">Data ini wajib diisi agar program Anda dapat diproses.</p>
                </div>
                <a href="{{ route('profile.index') }}" class="px-3 py-1.5 rounded-xl bg-brand text-white text-xs font-medium hover:opacity-90">Isi di Profil</a>
            </div>
        @endif
        {{-- ===== Pengumuman belum dibaca (banner) ===== --}}
        @foreach ($unreadAnnouncements as $a)
            <div class="px-4 py-3.5 rounded-card border border-status-pending/30 bg-status-pending/10 flex flex-wrap items-start gap-3">
                <span class="material-symbols-outlined icon-lg shrink-0">campaign</span>
                <div class="flex-1 min-w-0 text-sm">
                    <p class="font-semibold text-text-primary break-words">{{ $a->title }}</p>
                    <p class="text-text-secondary break-words">{{ $a->body }}</p>
                    <p class="text-xs text-text-secondary mt-1">Dari: {{ $a->sender?->name }} · {{ $a->created_at?->diffForHumans() }}</p>
                </div>
                <form method="POST" action="{{ route('announcements.read', $a) }}" class="w-full sm:w-auto sm:flex-shrink-0">
                    @csrf
                    <button class="w-full sm:w-auto px-3 py-1.5 rounded-xl bg-brand text-white text-xs font-medium hover:opacity-90">Tandai Dibaca</button>
                </form>
            </div>
        @endforeach

        {{-- ===== Health indicator (self-awareness) ===== --}}
        <div class="px-4 py-3 rounded-card border flex items-center gap-3
            {{ $regularity === 'green' ? 'bg-status-success/10 border-status-success/20 text-status-success' : '' }}
            {{ $regularity === 'yellow' ? 'bg-status-pending/10 border-status-pending/20 text-status-pending' : '' }}
            {{ $regularity === 'red' ? 'bg-status-danger/10 border-status-danger/20 text-status-danger' : '' }}">
            <span class="inline-block w-4 h-4 rounded-full flex-shrink-0
                {{ $regularity === 'green' ? 'bg-status-success' : '' }}
                {{ $regularity === 'yellow' ? 'bg-status-pending' : '' }}
                {{ $regularity === 'red' ? 'bg-status-danger' : '' }}"></span>
            <div class="text-sm">
                <strong>Status bimbingan Anda: {{ ucfirst($regularity) }}</strong>
                <span class="block text-xs opacity-80">{{ $regularityTooltip }}</span>
            </div>
        </div>

        {{-- ===== Milestone Journey (fase) ===== --}}
        <div class="card p-6">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                <h2 class="font-heading font-semibold text-text-primary">Milestone Journey</h2>
                <span class="text-sm text-text-secondary">{{ $ta->faseLabel() }} · {{ $progressPercent }}%</span>
            </div>
            @include('partials.milestone', ['faseKeys' => $faseKeys, 'faseIndex' => $faseIndex])
            <p class="mt-3 text-xs text-text-secondary">Fase ditetapkan oleh dosen pembimbing.</p>

            @php
                $isSeminarMilestone = $ta->isKp()
                    ? in_array($ta->fase, ['seminar_kp'])
                    : in_array($ta->fase, ['proposal', 'seminar_hasil', 'sidang']);
            @endphp
            @if ($isSeminarMilestone)
                <div class="mt-4 pt-4 border-t border-border">
                    @if ($seminarSubmission)
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-text-primary">Bahan {{ $seminarSubmission->jenisLabel() }}: {{ $seminarSubmission->statusLabel() }}</p>
                                <p class="text-xs text-text-secondary">Jadwal: {{ $seminarSubmission->tanggal->format('d M Y') }} · {{ $seminarSubmission->waktu?->format('H:i') }}</p>
                            </div>
                            <a href="{{ route('seminar-submission.show', $seminarSubmission) }}" class="px-3 py-1.5 rounded-xl bg-brand text-white text-xs font-medium hover:opacity-90">Lihat Detail</a>
                        </div>
                    @else
                        <a href="{{ route('seminar-submission.create', $ta) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-brand text-white text-sm font-medium hover:opacity-90">
                            <span class="material-symbols-outlined icon-sm">upload_file</span> Kirim Bahan {{ $ta->faseLabel() }}
                        </a>
                    @endif
                </div>
            @endif
        </div>

        {{-- ===== Agenda Terdekat ===== --}}
        @if ($agendaTerdekat->isNotEmpty())
            <div class="card p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-heading font-semibold text-text-primary">Agenda Terdekat</h2>
                </div>
                <div class="space-y-2">
                    @foreach ($agendaTerdekat as $agenda)
                        <a href="{{ route('seminar-submission.show', $agenda) }}" class="flex items-center gap-3 p-3 rounded-xl bg-bg-panel border border-border hover:border-brand/30 transition-colors">
                            <span class="icon-circle w-10 h-10 bg-brand-light text-brand">
                                <span class="material-symbols-outlined icon-md">event</span>
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-text-primary">{{ $agenda->jenisLabel() }}</p>
                                <p class="text-xs text-text-secondary">{{ $agenda->tanggal->format('d M Y') }} · {{ $agenda->waktu?->format('H:i') }}{{ $agenda->lokasi ? ' · ' . $agenda->lokasi : '' }}</p>
                            </div>
                            <span class="text-brand text-xs font-medium">Lihat →</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ===== Info Program + Progres ===== --}}
        <div class="grid md:grid-cols-3 gap-5">
            <div class="card p-6 md:col-span-2">
                @if ($ta->isKp())
                    <h2 class="font-heading font-semibold text-text-primary mb-1">Tempat Kerja Praktek</h2>
                    <p class="text-text-primary">{{ $ta->tempat_kp ?: 'Belum diisi' }}</p>
                    @if ($ta->periode_mulai)
                        <p class="text-sm text-text-secondary mt-1">Periode: {{ $ta->periode_mulai->format('d M Y') }} – {{ $ta->periode_selesai?->format('d M Y') ?? 'sekarang' }}</p>
                    @endif
                    @if ($ta->members->isNotEmpty())
                        <div class="mt-3">
                            <p class="text-xs text-text-secondary mb-1">Anggota Kelompok:</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($ta->allMembers() as $member)
                                    <span class="inline-block px-2 py-0.5 rounded-full text-xs bg-bg-panel border border-border">{{ $member->name }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @else
                    <h2 class="font-heading font-semibold text-text-primary mb-1">Judul TA</h2>
                    <p class="text-text-primary">{{ $ta->judul_ta }}</p>
                @endif
                <div class="mt-4 grid sm:grid-cols-2 gap-3 text-sm">
                    <div class="px-3 py-2.5 rounded-xl bg-bg-panel">
                        <span class="text-text-secondary">Pembimbing 1:</span>
                        @if ($ta->pembimbing1)
                            <a href="{{ route('profile.show', $ta->pembimbing1) }}" class="font-medium text-text-primary hover:text-brand hover:underline">{{ $ta->pembimbing1->name }}</a>
                        @else
                            <span class="font-medium text-text-primary">Belum ditentukan</span>
                        @endif
                    </div>
                    <div class="px-3 py-2.5 rounded-xl bg-bg-panel">
                        <span class="text-text-secondary">Pembimbing 2:</span>
                        @if ($ta->pembimbing2)
                            <a href="{{ route('profile.show', $ta->pembimbing2) }}" class="font-medium text-text-primary hover:text-brand hover:underline">{{ $ta->pembimbing2->name }}</a>
                        @else
                            <span class="font-medium text-text-primary">Belum ditentukan</span>
                        @endif
                    </div>
                    @if ($ta->isKp())
                        <div class="px-3 py-2.5 rounded-xl bg-bg-panel">
                            <span class="text-text-secondary">Pembimbing Lapangan:</span>
                            <span class="font-medium text-text-primary">{{ $ta->pembimbing_lapangan ?: 'Belum ditentukan' }}</span>
                        </div>
                    @else
                        <div class="px-3 py-2.5 rounded-xl bg-bg-panel">
                            <span class="text-text-secondary">Penguji 1:</span>
                            @if ($ta->penguji1)
                                <a href="{{ route('profile.show', $ta->penguji1) }}" class="font-medium text-text-primary hover:text-brand hover:underline">{{ $ta->penguji1->name }}</a>
                            @else
                                <span class="font-medium text-text-primary">Belum ditentukan</span>
                            @endif
                        </div>
                        <div class="px-3 py-2.5 rounded-xl bg-bg-panel">
                            <span class="text-text-secondary">Penguji 2:</span>
                            @if ($ta->penguji2)
                                <a href="{{ route('profile.show', $ta->penguji2) }}" class="font-medium text-text-primary hover:text-brand hover:underline">{{ $ta->penguji2->name }}</a>
                            @else
                                <span class="font-medium text-text-primary">Belum ditentukan</span>
                            @endif
                        </div>
                    @endif
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    @if ($ta->pembimbing1 || $ta->pembimbing2)
                        <a href="{{ route('logbook.export.pdf', $ta) }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">Rekap PDF</a>
                        <a href="{{ route('logbook.export.excel', $ta) }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">Excel</a>
                    @endif
                    @if ($ta->isKp())
                        <a href="{{ route('logbook-harian.index', $ta) }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">Logbook Harian</a>
                        <a href="{{ route('profil-perusahaan.index', $ta) }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">Profil Perusahaan</a>
                    @endif
                </div>
            </div>

            <div class="card p-6">
                <h2 class="font-heading font-semibold text-text-primary mb-3">Progres Bimbingan</h2>
                <div class="flex items-end justify-between mb-1 text-sm">
                    <span class="text-text-secondary">{{ $approved }} / {{ $target }} sesi disetujui</span>
                    <span class="font-bold text-text-primary">{{ $progressPercent }}%</span>
                </div>
                <div class="h-3 rounded-full bg-bg-panel overflow-hidden">
                    <div class="progress-bar h-full rounded-full bg-brand" style="width:{{ $progressPercent }}%"></div>
                </div>
                <p class="mt-3 text-xs text-text-secondary">Minimal {{ $target }} sesi bimbingan perlu disetujui.</p>
            </div>
        </div>

        {{-- ===== Logbook Harian (khusus KP) ===== --}}
        @if ($ta->isKp())
            <div class="card p-6">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <h2 class="font-heading font-semibold text-text-primary">Logbook Harian KP</h2>
                    <a href="{{ route('logbook-harian.index', $ta) }}" class="text-sm text-brand hover:underline">Lihat Semua →</a>
                </div>
                @if ($logbookHarian->isEmpty())
                    <p class="text-sm text-text-secondary">Belum ada catatan harian.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($logbookHarian->take(5) as $lh)
                            <div class="px-3 py-2.5 rounded-xl bg-bg-panel">
                                <div class="text-xs font-semibold text-text-secondary">{{ $lh->tanggal->format('d M Y') }}</div>
                                <p class="text-sm mt-0.5">{{ \Illuminate\Support\Str::limit($lh->kegiatan, 120) }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- ===== Badge + Statistik ===== --}}
        <div class="grid lg:grid-cols-2 gap-5">
            @if ($ta->isTa())
                <div class="card p-6">
                    <h2 class="font-heading font-semibold text-text-primary mb-3">Achievement ({{ $unlockedAchievements->count() }}/{{ $totalAchievements }})</h2>
                    @include('partials.badge-shelf', ['unlockedAchievements' => $unlockedAchievements, 'unlockedCodes' => $unlockedCodes, 'totalAchievements' => $totalAchievements])
                </div>
            @endif

            <div class="card p-6">
                <h2 class="font-heading font-semibold text-text-primary mb-3">Statistik & Streak</h2>
                @include('partials.stat-cards', ['stats' => $stats])
            </div>
        </div>

        {{-- ===== Heatmap ===== --}}
        <div class="card p-6">
            <h2 class="font-heading font-semibold text-text-primary mb-3">Aktivitas 12 Bulan</h2>
            @include('partials.heatmap', ['heatmap' => $heatmap])
        </div>

        {{-- ===== Timeline ===== --}}
        <div class="card p-6">
            <h2 class="font-heading font-semibold text-text-primary mb-4">Timeline Bimbingan</h2>
            @include('partials.timeline', ['timeline' => $timeline, 'regularity' => $regularity, 'regularityTooltip' => $regularityTooltip])
        </div>
    @endif
</div>
@endsection
