@extends('layouts.app')

@section('title', 'Dashboard Admin')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading font-bold text-2xl text-text-primary">Dashboard Admin</h1>
            <p class="text-sm text-text-secondary mt-0.5">Ringkasan aktivitas bimbingan TA</p>
        </div>
    </div>

    {{-- ===== Stat cards (icon-circle + delta badge) ===== --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
        <div class="p-5 rounded-card bg-bg-surface border border-border">
            <div class="flex items-center justify-between mb-3">
                <span class="icon-circle w-10 h-10 bg-brand-light text-brand">
                    <span class="material-symbols-outlined icon-md">group</span>
                </span>
            </div>
            <div class="font-heading font-bold text-3xl text-text-primary tabular-nums">{{ $stats['mahasiswa'] }}</div>
            <div class="text-sm text-text-secondary mt-1">Mahasiswa</div>
        </div>
        <div class="p-5 rounded-card bg-bg-surface border border-border">
            <div class="flex items-center justify-between mb-3">
                <span class="icon-circle w-10 h-10 bg-brand-light text-brand">
                    <span class="material-symbols-outlined icon-md">school</span>
                </span>
            </div>
            <div class="font-heading font-bold text-3xl text-text-primary tabular-nums">{{ $stats['dosen'] }}</div>
            <div class="text-sm text-text-secondary mt-1">Dosen</div>
        </div>
        <div class="p-5 rounded-card bg-bg-surface border border-border">
            <div class="flex items-center justify-between mb-3">
                <span class="icon-circle w-10 h-10 bg-sand/15 text-sand">
                    <span class="material-symbols-outlined icon-md">archive</span>
                </span>
            </div>
            <div class="font-heading font-bold text-3xl text-text-primary tabular-nums">{{ $stats['ta'] }}</div>
            <div class="text-sm text-text-secondary mt-1">Data TA</div>
        </div>
        <div class="p-5 rounded-card bg-bg-surface border border-border">
            <div class="flex items-center justify-between mb-3">
                <span class="icon-circle w-10 h-10 bg-status-danger/15 text-status-danger">
                    <span class="material-symbols-outlined icon-md">fact_check</span>
                </span>
            </div>
            <div class="font-heading font-bold text-3xl text-text-primary tabular-nums">{{ $stats['menunggu_review'] }}</div>
            <div class="text-sm text-text-secondary mt-1">Menunggu Review</div>
        </div>
    </div>

    {{-- Import mahasiswa via Excel --}}
    <div class="card p-6">
        <h2 class="font-heading font-semibold text-text-primary mb-3">Import Mahasiswa (Excel)</h2>
        <form method="POST" action="{{ route('admin.import-mahasiswa') }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="w-full sm:w-auto">
                <label class="block text-xs text-text-secondary mb-1">File Excel (nama, nim, email, pembimbing1_nidn, pembimbing2_nidn)</label>
                <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="text-sm w-full sm:w-auto">
            </div>
            <div class="w-full sm:w-auto">
                <label class="block text-xs text-text-secondary mb-1">Pembimbing Default</label>
                <select name="pembimbing_default" class="w-full sm:w-auto rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                    @foreach (\App\Models\User::role('dosen')->get() as $d)
                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="px-4 py-2 rounded-xl bg-brand text-white text-sm font-medium">Import</button>
            @error('file')
                <p class="text-status-danger text-xs w-full">{{ $message }}</p>
            @enderror
        </form>
    </div>

    <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-heading font-semibold text-text-primary">Data TA Terbaru</h2>
            <a href="{{ route('admin.tas') }}" class="text-sm text-brand hover:underline">Kelola →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-text-secondary border-b border-border">
                        <th class="py-2 pr-4 font-medium">Mahasiswa</th>
                        <th class="py-2 pr-4 font-medium hidden md:table-cell">Judul</th>
                        <th class="py-2 pr-4 font-medium hidden sm:table-cell">Pembimbing</th>
                        <th class="py-2 font-medium hidden sm:table-cell">Entri</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tas as $ta)
                        <tr class="border-b border-border last:border-0">
                            <td class="py-2.5 pr-4">{{ $ta->mahasiswa?->name }}</td>
                            <td class="py-2.5 pr-4 hidden md:table-cell">{{ $ta->judul_ta }}</td>
                            <td class="py-2.5 pr-4 text-xs hidden sm:table-cell">{{ $ta->pembimbing1?->name }}{{ $ta->pembimbing2 ? ' & ' . $ta->pembimbing2->name : '' }}</td>
                            <td class="py-2.5 hidden sm:table-cell">{{ $ta->entries_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
