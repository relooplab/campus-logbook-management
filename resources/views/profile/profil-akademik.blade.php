@extends('layouts.app')

@section('title', 'Profil Akademik')

@section('content')
<div class="max-w-3xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading font-bold text-2xl text-text-primary">Profil Akademik</h1>
            <p class="text-sm text-text-secondary mt-0.5">Ringkasan program (TA/KP) dan dosen Anda</p>
        </div>
        <a href="{{ route('dashboard') }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">← Dashboard</a>
    </div>

    @if (session('success'))
        <div class="px-4 py-3 rounded-xl bg-status-success/10 text-status-success border border-status-success/20">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="px-4 py-3 rounded-xl bg-status-danger/10 text-status-danger border border-status-danger/20">{{ session('error') }}</div>
    @endif

    {{-- ===== Tugas Akhir ===== --}}
    <div class="card p-6">
        <h2 class="font-heading font-semibold text-text-primary mb-4">Tugas Akhir</h2>
        @if ($ta && $ta->status_ta === 'aktif')
            <div class="space-y-3 text-sm">
                <div class="grid sm:grid-cols-2 gap-3">
                    <div class="px-3 py-2.5 rounded-xl bg-bg-panel">
                        <p class="text-xs text-text-secondary mb-0.5">Judul Penelitian</p>
                        <p class="font-medium text-text-primary">{{ $ta->judul_ta ?: '—' }}</p>
                    </div>
                    <div class="px-3 py-2.5 rounded-xl bg-bg-panel">
                        <p class="text-xs text-text-secondary mb-0.5">Fase</p>
                        <p class="font-medium text-text-primary">{{ $ta->faseLabel() }}</p>
                    </div>
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <div class="px-3 py-2.5 rounded-xl bg-bg-panel">
                        <p class="text-xs text-text-secondary mb-0.5">Pembimbing</p>
                        <p class="font-medium text-text-primary">{{ collect([$ta->pembimbing1?->name, $ta->pembimbing2?->name])->filter()->implode(' · ') ?: '—' }}</p>
                    </div>
                    <div class="px-3 py-2.5 rounded-xl bg-bg-panel">
                        <p class="text-xs text-text-secondary mb-0.5">Penguji</p>
                        <p class="font-medium text-text-primary">{{ collect([$ta->penguji1?->name, $ta->penguji2?->name])->filter()->implode(' · ') ?: 'Belum ada' }}</p>
                    </div>
                </div>
            </div>

            @include('profile.partials.usul-penguji', ['program' => $ta])
        @elseif ($ta && $ta->status_ta === 'pending_approval')
            <p class="text-sm text-text-secondary bg-bg-panel border border-border rounded-xl px-4 py-3">
                Permintaan Anda sedang menunggu persetujuan dosen pembimbing. Sementara itu, Anda tetap bisa mengirim bahan seminar/sidang.
            </p>
        @elseif ($ta && $ta->status_ta === 'ditolak')
            <p class="text-sm text-text-secondary bg-bg-panel border border-border rounded-xl px-4 py-3">
                Permintaan Anda ditolak dosen{{ $ta->alasan_ditolak ? ': "'.$ta->alasan_ditolak.'"' : '' }}. Silakan pilih dosen lain.
            </p>
            <a href="{{ route('profile.select-dosen') }}" class="inline-block mt-3 px-4 py-2 rounded-xl bg-brand text-[#0b1420] text-sm font-medium hover:opacity-90">Pilih Dosen</a>
        @elseif ($ta)
            <p class="text-sm text-text-secondary bg-bg-panel border border-border rounded-xl px-4 py-3">
                Program TA Anda berstatus <span class="font-medium">{{ ucfirst($ta->status_ta) }}</span>.
            </p>
        @else
            <p class="text-sm text-text-secondary bg-bg-panel border border-border rounded-xl px-4 py-3">
                Belum ada program TA. Mulai dengan memilih dosen pembimbing untuk program TA Anda.
            </p>
            <a href="{{ route('profile.select-dosen') }}" class="inline-block mt-3 px-4 py-2 rounded-xl bg-brand text-[#0b1420] text-sm font-medium hover:opacity-90">Pilih Dosen</a>
        @endif
    </div>

    {{-- ===== Kerja Praktik ===== --}}
    <div class="card p-6">
        <h2 class="font-heading font-semibold text-text-primary mb-4">Kerja Praktik</h2>
        @if ($kp && $kp->status_ta === 'aktif')
            <div class="space-y-3 text-sm">
                <div class="grid sm:grid-cols-2 gap-3">
                    <div class="px-3 py-2.5 rounded-xl bg-bg-panel">
                        <p class="text-xs text-text-secondary mb-0.5">Judul KP</p>
                        <p class="font-medium text-text-primary">{{ $kp->judul_ta ?: '—' }}</p>
                    </div>
                    <div class="px-3 py-2.5 rounded-xl bg-bg-panel">
                        <p class="text-xs text-text-secondary mb-0.5">Lokasi</p>
                        <p class="font-medium text-text-primary">{{ $kp->tempat_kp ?: '—' }}</p>
                    </div>
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <div class="px-3 py-2.5 rounded-xl bg-bg-panel">
                        <p class="text-xs text-text-secondary mb-0.5">Pembimbing</p>
                        <p class="font-medium text-text-primary">{{ collect([$kp->pembimbing1?->name, $kp->pembimbing2?->name])->filter()->implode(' · ') ?: '—' }}</p>
                    </div>
                    <div class="px-3 py-2.5 rounded-xl bg-bg-panel">
                        <p class="text-xs text-text-secondary mb-0.5">Penguji</p>
                        <p class="font-medium text-text-primary">{{ collect([$kp->penguji1?->name, $kp->penguji2?->name])->filter()->implode(' · ') ?: 'Belum ada' }}</p>
                    </div>
                </div>
            </div>

            @include('profile.partials.usul-penguji', ['program' => $kp])
        @elseif ($kp && $kp->status_ta === 'pending_approval')
            <p class="text-sm text-text-secondary bg-bg-panel border border-border rounded-xl px-4 py-3">
                Permintaan Anda sedang menunggu persetujuan dosen pembimbing.
            </p>
        @elseif ($kp && $kp->status_ta === 'ditolak')
            <p class="text-sm text-text-secondary bg-bg-panel border border-border rounded-xl px-4 py-3">
                Permintaan Anda ditolak dosen{{ $kp->alasan_ditolak ? ': "'.$kp->alasan_ditolak.'"' : '' }}. Silakan pilih dosen lain.
            </p>
            <a href="{{ route('profile.select-dosen') }}" class="inline-block mt-3 px-4 py-2 rounded-xl bg-brand text-[#0b1420] text-sm font-medium hover:opacity-90">Pilih Dosen</a>
        @elseif ($kp)
            <p class="text-sm text-text-secondary bg-bg-panel border border-border rounded-xl px-4 py-3">
                Program KP Anda berstatus <span class="font-medium">{{ ucfirst($kp->status_ta) }}</span>.
            </p>
        @else
            <p class="text-sm text-text-secondary bg-bg-panel border border-border rounded-xl px-4 py-3">
                Belum ada program KP. Mulai dengan memilih dosen pembimbing untuk program KP Anda.
            </p>
            <a href="{{ route('profile.select-dosen') }}" class="inline-block mt-3 px-4 py-2 rounded-xl bg-brand text-[#0b1420] text-sm font-medium hover:opacity-90">Pilih Dosen</a>
        @endif
    </div>
</div>
@endsection