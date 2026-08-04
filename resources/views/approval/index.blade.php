@extends('layouts.app')

@section('title', 'Persetujuan Attachment Dosen')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading font-bold text-2xl text-text-primary">Persetujuan Attachment Dosen</h1>
            <p class="text-sm text-text-secondary mt-0.5">Setujui mahasiswa yang memilih Anda sebagai dosen</p>
        </div>
        <a href="{{ route('dashboard') }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border text-center">← Dashboard</a>
    </div>

    {{-- Tambah Mahasiswa Manual (input email saja) --}}
    <div class="card p-6">
        <div class="flex items-center gap-3 mb-4">
            <span class="icon-circle w-10 h-10 bg-brand-light text-brand">
                <span class="material-symbols-outlined icon-md">person_add</span>
            </span>
            <div>
                <h2 class="font-heading font-semibold text-text-primary">Tambah Mahasiswa Manual</h2>
                <p class="text-sm text-text-secondary">Masukkan email mahasiswa. Mahasiswa perlu verifikasi email & memilih dosen.</p>
            </div>
        </div>
        <form method="POST" action="{{ route('approval.invite') }}" class="flex flex-col sm:flex-row gap-3">
            @csrf
            <div class="flex-1">
                <label for="invite-email" class="block text-xs text-text-secondary mb-1">Email mahasiswa</label>
                <input type="email" name="email" id="invite-email" required placeholder="nama@email.com"
                    class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm text-text-primary placeholder:text-text-secondary focus:outline-none focus:ring-2 focus:ring-brand/40">
                @error('email')
                    <p class="text-xs text-status-danger mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full sm:w-auto px-4 py-2 rounded-xl bg-brand text-white text-sm font-medium hover:opacity-90 inline-flex items-center justify-center gap-1.5">
                    <span class="material-symbols-outlined icon-sm">add</span> Tambah
                </button>
            </div>
        </form>
    </div>

    {{-- Daftar Permintaan Pending --}}
    <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-heading font-semibold text-text-primary">Permintaan Menunggu ({{ $pending->count() }})</h2>
        </div>

        @if ($pending->isEmpty())
            <div class="px-4 py-10 rounded-xl bg-bg-panel border border-border text-center text-text-secondary">
                <span class="material-symbols-outlined icon-lg mb-2 text-text-secondary/50">check_circle</span>
                <p>Tidak ada permintaan attachment yang menunggu.</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach ($pending as $ta)
                    @php $m = $ta->mahasiswa; @endphp
                    <div class="bg-bg-panel rounded-xl border border-border p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 rounded-full bg-brand-light text-brand flex items-center justify-center text-xs font-bold shrink-0">
                                    @if ($m?->photoUrl())
                                        <img src="{{ $m->photoUrl() }}" class="h-full w-full object-cover rounded-full" alt="{{ $m->name }}">
                                    @else
                                        {{ $m?->initials() ?? '?' }}
                                    @endif
                                </div>
                                <div>
                                    <p class="font-semibold text-text-primary">{{ $m?->name ?? 'Mahasiswa' }}</p>
                                    <p class="text-sm text-text-secondary">{{ $m?->email }}</p>
                                    <p class="text-xs text-text-secondary mt-0.5">Program: {{ $ta->jenisLabel() }} · Daftar {{ $ta->created_at?->format('d M Y, H:i') }}</p>
                                    <p class="text-xs text-text-secondary mt-1">
                                        Memilih: 
                                        @if ($ta->pembimbing1) <span class="inline-block px-1.5 py-0.5 rounded bg-bg-surface border border-border mr-1">P1: {{ $ta->pembimbing1->name }}</span> @endif
                                        @if ($ta->pembimbing2) <span class="inline-block px-1.5 py-0.5 rounded bg-bg-surface border border-border mr-1">P2: {{ $ta->pembimbing2->name }}</span> @endif
                                        @if ($ta->penguji1) <span class="inline-block px-1.5 py-0.5 rounded bg-bg-surface border border-border mr-1">U1: {{ $ta->penguji1->name }}</span> @endif
                                        @if ($ta->penguji2) <span class="inline-block px-1.5 py-0.5 rounded bg-bg-surface border border-border mr-1">U2: {{ $ta->penguji2->name }}</span> @endif
                                    </p>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-status-pending/10 text-status-pending">
                                <span class="w-1.5 h-1.5 rounded-full bg-status-pending"></span> Pending
                            </span>
                        </div>

                        <form method="POST" action="{{ route('approval.approve', $ta) }}" class="grid sm:grid-cols-2 gap-3">
                            @csrf
                            <div class="sm:col-span-2">
                                <label class="block text-xs text-text-secondary mb-1">{{ $ta->isKp() ? 'Tempat KP (opsional)' : 'Judul TA (opsional)' }}</label>
                                <input type="text" name="{{ $ta->isKp() ? 'tempat_kp' : 'judul_ta' }}" value="{{ $ta->isKp() ? $ta->tempat_kp : $ta->judul_ta }}" placeholder="Boleh diisi mahasiswa nanti via profil"
                                    class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                            </div>
                            <div>
                                <label class="block text-xs text-text-secondary mb-1">Peran Anda untuk mahasiswa ini</label>
                                <select name="role_dosen"
                                    class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                                    <option value="pembimbing_1" @selected($ta->pembimbing_1_id === auth()->id())>Pembimbing 1</option>
                                    <option value="pembimbing_2" @selected($ta->pembimbing_2_id === auth()->id())>Pembimbing 2</option>
                                    <option value="penguji_1" @selected($ta->penguji_1_id === auth()->id())>Penguji 1</option>
                                    <option value="penguji_2" @selected($ta->penguji_2_id === auth()->id())>Penguji 2</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-text-secondary mb-1">Target Sesi</label>
                                <input type="number" name="target_sesi" value="{{ $ta->target_sesi ?? 7 }}" min="1"
                                    class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                            </div>
                            <div class="sm:col-span-2 flex flex-wrap gap-2 pt-1">
                                <button type="submit" class="px-4 py-2 rounded-xl bg-brand text-white text-sm font-medium hover:opacity-90 inline-flex items-center gap-1.5">
                                    <span class="material-symbols-outlined icon-sm">check_circle</span> Setujui & Assign
                                </button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('approval.reject', $ta) }}" class="mt-2" onsubmit="return confirm('Tolak permintaan {{ $m?->name }}?')">
                            @csrf
                            <button type="submit" class="px-4 py-2 rounded-xl bg-status-danger/10 text-status-danger text-sm font-medium hover:bg-status-danger/20 inline-flex items-center gap-1.5">
                                <span class="material-symbols-outlined icon-sm">close</span> Tolak
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection