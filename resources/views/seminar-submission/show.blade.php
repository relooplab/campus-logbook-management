@extends('layouts.app')

@section('title', 'Detail Bahan '.$submission->jenisLabel())

@section('content')
<div class="max-w-2xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading font-bold text-2xl text-text-primary">Detail Bahan {{ $submission->jenisLabel() }}</h1>
            <p class="text-sm text-text-secondary mt-0.5">
                {{ $submission->mahasiswaTa->mahasiswa?->name }} · {{ $submission->statusLabel() }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($isMember && !$submission->sidang_id)
                <a href="{{ route('seminar-submission.edit', $submission) }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">Edit</a>
            @endif
            <a href="{{ route('dashboard') }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">← Dashboard</a>
        </div>
    </div>

    @if (session('success'))
        <div class="px-4 py-3 rounded-xl bg-status-success/10 text-status-success border border-status-success/20">
            {{ session('success') }}
        </div>
    @endif

    {{-- ===== Jadwal ===== --}}
    <div class="card p-6">
        <h2 class="font-heading font-semibold text-text-primary mb-4">Rencana Jadwal</h2>
        <div class="grid sm:grid-cols-2 gap-4 text-sm">
            <div class="px-3 py-2.5 rounded-xl bg-bg-panel">
                <span class="text-text-secondary">Tanggal:</span>
                <span class="font-medium text-text-primary">{{ $submission->tanggal->format('d M Y') }}</span>
            </div>
            <div class="px-3 py-2.5 rounded-xl bg-bg-panel">
                <span class="text-text-secondary">Waktu:</span>
                <span class="font-medium text-text-primary">{{ $submission->waktu?->format('H:i') }}</span>
            </div>
            <div class="px-3 py-2.5 rounded-xl bg-bg-panel sm:col-span-2">
                <span class="text-text-secondary">Lokasi/Link:</span>
                <span class="font-medium text-text-primary">{{ $submission->lokasi ?: '—' }}</span>
            </div>
        </div>
    </div>

    {{-- ===== Surat Undangan ===== --}}
    <div class="card p-6">
        <h2 class="font-heading font-semibold text-text-primary mb-4">Surat Undangan</h2>
        <div class="flex flex-wrap items-center justify-between gap-3 p-3 rounded-xl bg-bg-panel border border-border">
            <div class="flex items-center gap-3 min-w-0">
                <span class="text-2xl">📄</span>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-text-primary truncate">{{ $submission->undangan_original_name }}</p>
                    <p class="text-xs text-text-secondary">Undangan sebagai: {{ $submission->undanganSebagaiLabel() }}</p>
                </div>
            </div>
            <a href="{{ route('seminar-submission.undangan-download', $submission) }}" class="px-3 py-1.5 rounded-xl bg-brand text-[#0b1420] text-xs font-medium hover:opacity-90">Download</a>
        </div>
    </div>

    {{-- ===== Dokumen Materi ===== --}}
    <div class="card p-6">
        <h2 class="font-heading font-semibold text-text-primary mb-4">Dokumen Materi</h2>
        @if ($submission->materi_path)
            <div class="flex flex-wrap items-center justify-between gap-3 p-3 rounded-xl bg-bg-panel border border-border">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="text-2xl">📕</span>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-text-primary truncate">{{ $submission->materi_original_name }}</p>
                        <p class="text-xs text-text-secondary">
                            {{ $submission->materiFromWorkspace() ? 'Dari workspace' : 'Upload baru' }}
                        </p>
                    </div>
                </div>
                <a href="{{ route('seminar-submission.materi-download', $submission) }}" class="px-3 py-1.5 rounded-xl bg-brand text-[#0b1420] text-xs font-medium hover:opacity-90">Download</a>
            </div>
        @else
            <p class="text-sm text-text-secondary">Belum ada dokumen materi.</p>
        @endif
    </div>

    {{-- ===== Catatan Hardcopy ===== --}}
    <div class="card p-6">
        <h2 class="font-heading font-semibold text-text-primary mb-4">Catatan Hardcopy</h2>
        <p class="text-sm text-text-secondary whitespace-pre-line mb-3">{{ $submission->catatan_hardcopy }}</p>

        @if ($isDosen)
            <form method="POST" action="{{ route('seminar-submission.hardcopy-note', $submission) }}" class="space-y-2">
                @csrf
                @method('PUT')
                <textarea name="catatan_hardcopy" rows="3" class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm">{{ $submission->catatan_hardcopy }}</textarea>
                <button type="submit" class="px-3 py-1.5 rounded-xl bg-bg-hover text-text-primary text-xs font-medium hover:bg-border">Simpan Catatan</button>
            </form>
        @endif
    </div>

    {{-- ===== Catatan Keterangan ===== --}}
    @if ($submission->catatan_keterangan)
        <div class="card p-6">
            <h2 class="font-heading font-semibold text-text-primary mb-2">Catatan Keterangan</h2>
            <p class="text-sm text-text-secondary whitespace-pre-line">{{ $submission->catatan_keterangan }}</p>
        </div>
    @endif

    {{-- ===== Catat Hasil Sidang/Seminar (dosen) ===== --}}
    @if ($isDosen && !$submission->sidang_id)
        <div class="card p-6">
            <h2 class="font-heading font-semibold text-text-primary mb-2">Catat Hasil Sidang / Seminar</h2>
            <p class="text-sm text-text-secondary mb-3">Setelah sidang/seminar berlangsung, catat hasilnya ke Riwayat Sidang. Pembimbing & penguji akan mengisi nilai.</p>
            <a href="{{ route('dosen-sidang.index', ['submission' => $submission->id]) }}"
                class="inline-block px-4 py-2 rounded-xl bg-brand text-[#0b1420] text-sm font-medium hover:opacity-90">Catat Hasil Sidang / Seminar</a>
        </div>
    @endif

    {{-- ===== Sudah dicatat + nilai ===== --}}
    @if ($submission->sidang_id && $submission->sidang)
        @php $sidangR = $submission->sidang; @endphp
        <div class="card p-6">
            <h2 class="font-heading font-semibold text-text-primary mb-3">Hasil & Nilai {{ $sidangR->jenisLabel() }}</h2>
            <p class="text-sm mb-2">Hasil: <span class="font-medium">{{ $sidangR->hasilLabel() }}</span></p>
            @php $grades = $sidangR->loadMissing('grades.user')->grades; @endphp
            @if ($grades->isNotEmpty())
                <ul class="space-y-1 text-sm">
                    @foreach ($grades as $g)
                        <li class="flex items-center justify-between gap-2">
                            <span>{{ $g->user?->name }} <span class="text-xs text-text-secondary">({{ ucfirst($g->role) }})</span></span>
                            <span class="font-medium">{{ $g->filled_at ? $g->nilai : 'Belum dinilai' }}</span>
                        </li>
                    @endforeach
                </ul>
                @if ($sidangR->nilaiFinal() !== null)
                    <p class="mt-2 font-semibold text-text-primary">Rerata: {{ $sidangR->nilaiFinal() }}</p>
                @else
                    <p class="mt-2 text-xs text-text-secondary">Nilai belum lengkap — menunggu dosen terkait melengkapi.</p>
                @endif
            @else
                <p class="text-xs text-text-secondary">Belum ada penilaian dicatat untuk sidang ini.</p>
            @endif
        </div>
    @endif
</div>
@endsection