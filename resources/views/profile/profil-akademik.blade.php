@extends('layouts.app')

@section('title', 'Profil Akademik')

@section('content')
<div class="profile-workspace space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading font-bold text-2xl text-text-primary">Profil Akademik</h1>
            <p class="text-sm text-text-secondary mt-0.5">Ringkasan program (TA/KP) dan dosen Anda</p>
        </div>
        <a href="{{ route('dashboard') }}" class="btn-secondary px-4 py-2.5 text-sm font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">← Dashboard</a>
    </div>

    @if ($errors->any())
        <div role="alert" class="rounded-xl border border-status-danger/20 bg-status-danger/10 p-4 text-sm text-status-danger"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <div class="academic-workspace-grid">
    <div class="min-w-0 space-y-6">
    {{-- ===== Tugas Akhir ===== --}}
    <div class="card min-w-0 p-5 sm:p-6">
        <div class="mb-5 flex items-center gap-3"><span class="icon-chip h-11 w-11"><span class="material-symbols-outlined icon-lg" aria-hidden="true">school</span></span><div><h2 class="font-heading font-semibold text-text-primary">Tugas Akhir</h2><p class="text-sm text-text-secondary">Informasi program Tugas Akhir (TA) Anda.</p></div></div>
        @if ($ta && $ta->status_ta === 'aktif')
            <div class="grid gap-3 text-sm sm:grid-cols-2">
                    <div class="min-w-0 break-words rounded-xl border border-border bg-bg-panel px-4 py-3">
                        <p class="text-xs text-text-secondary mb-0.5">Judul Penelitian</p>
                        <p class="font-medium text-text-primary">{{ $ta->judul_ta ?: 'Belum tersedia' }}</p>
                    </div>
                    <div class="min-w-0 break-words rounded-xl border border-border bg-bg-panel px-4 py-3">
                        <p class="text-xs text-text-secondary mb-0.5">Fase</p>
                        <p class="font-medium text-text-primary">{{ $ta->faseLabel() }}</p>
                    </div>
                    <div class="min-w-0 break-words rounded-xl border border-border bg-bg-panel px-4 py-3">
                        <p class="text-xs text-text-secondary mb-0.5">Pembimbing</p>
                        <p class="font-medium text-text-primary">{{ collect([$ta->pembimbing1?->name, $ta->pembimbing2?->name])->filter()->implode(' · ') ?: 'Belum ditetapkan' }}</p>
                    </div>
                    <div class="min-w-0 break-words rounded-xl border border-border bg-bg-panel px-4 py-3">
                        <p class="text-xs text-text-secondary mb-0.5">Penguji</p>
                        <p class="font-medium text-text-primary">{{ collect([$ta->penguji1?->name, $ta->penguji2?->name])->filter()->implode(' · ') ?: 'Belum ditetapkan' }}</p>
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
    <div class="card min-w-0 p-5 sm:p-6">
        <div class="mb-5 flex items-center gap-3"><span class="icon-chip h-11 w-11"><span class="material-symbols-outlined icon-lg" aria-hidden="true">work</span></span><div><h2 class="font-heading font-semibold text-text-primary">Kerja Praktik</h2><p class="text-sm text-text-secondary">Informasi program Kerja Praktik (KP) Anda.</p></div></div>
        @if ($kp && $kp->status_ta === 'aktif')
            <div class="grid gap-3 text-sm sm:grid-cols-2">
                    <div class="min-w-0 break-words rounded-xl border border-border bg-bg-panel px-4 py-3">
                        <p class="text-xs text-text-secondary mb-0.5">Judul KP</p>
                        <p class="font-medium text-text-primary">{{ $kp->judul_ta ?: 'Belum tersedia' }}</p>
                    </div>
                    <div class="min-w-0 break-words rounded-xl border border-border bg-bg-panel px-4 py-3">
                        <p class="text-xs text-text-secondary mb-0.5">Lokasi</p>
                        <p class="font-medium text-text-primary">{{ $kp->tempat_kp ?: 'Belum tersedia' }}</p>
                    </div>
                    <div class="min-w-0 break-words rounded-xl border border-border bg-bg-panel px-4 py-3">
                        <p class="text-xs text-text-secondary mb-0.5">Pembimbing</p>
                        <p class="font-medium text-text-primary">{{ collect([$kp->pembimbing1?->name, $kp->pembimbing2?->name])->filter()->implode(' · ') ?: 'Belum ditetapkan' }}</p>
                    </div>
                    <div class="min-w-0 break-words rounded-xl border border-border bg-bg-panel px-4 py-3">
                        <p class="text-xs text-text-secondary mb-0.5">Penguji</p>
                        <p class="font-medium text-text-primary">{{ collect([$kp->penguji1?->name, $kp->penguji2?->name])->filter()->implode(' · ') ?: 'Belum ditetapkan' }}</p>
                    </div>
            </div>
                <section class="mt-5 border-t border-border pt-4" aria-labelledby="kp-members-title">
                    <h3 id="kp-members-title" class="mb-3 text-sm font-semibold">Anggota Kelompok</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($kp->allMembers() as $member)
                            <span class="inline-flex max-w-full items-center gap-1.5 rounded-full border border-border bg-bg-panel px-3 py-1.5 text-xs">
                                <span class="material-symbols-outlined icon-sm shrink-0 text-brand" aria-hidden="true">person</span><span class="min-w-0 break-words">
                                {{ $member->name }}
                                </span>
                                @if ($canManageKpMembers && $member->id !== $kp->user_id)
                                    <form method="POST" action="{{ route('profile.kp.remove-member', [$kp, $member]) }}"
                                        onsubmit="return confirm('Hapus anggota ini dari kelompok?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="rounded-full p-1 text-status-danger hover:bg-status-danger/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-status-danger" title="Hapus anggota" aria-label="Hapus {{ $member->name }} dari kelompok">&times;</button>
                                    </form>
                                @endif
                            </span>
                        @endforeach
                    </div>
                </section>
                @if ($canManageKpMembers)
                    <form method="POST" action="{{ route('profile.kp.add-member', $kp) }}" class="mt-4">
                        @csrf
                        <label for="kp-add-member" class="mb-2 block text-sm font-medium">Tambah teman ke kelompok</label>
                        <div class="flex flex-col gap-2 sm:flex-row"><select id="kp-add-member" name="user_id" required
                            class="min-w-0 w-full flex-1 rounded-xl border border-border bg-bg-surface px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                            <option value="">+ Tambah teman ke kelompok...</option>
                            @foreach ($kpEligibleMembers as $cand)
                                <option value="{{ $cand->id }}">{{ $cand->name }} ({{ $cand->nim }})</option>
                            @endforeach
                        </select>
                        <button class="btn-primary px-4 py-2.5 text-sm font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Tambah</button></div>
                    </form>
                    @if ($kpEligibleMembers->isEmpty())
                        <p class="text-xs text-text-secondary">Belum ada mahasiswa yang dapat ditambahkan (semua sudah terlibat KP lain).</p>
                    @endif
                @endif
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
    @include('profile.partials.academic-context')
    </div>
</div>
@endsection