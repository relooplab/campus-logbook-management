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
                <span class="icon-circle w-10 h-10 bg-accent-blue/15 text-accent-blue">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </span>
            </div>
            <div class="font-heading font-bold text-3xl text-text-primary tabular-nums">{{ $stats['mahasiswa'] }}</div>
            <div class="text-sm text-text-secondary mt-1">Mahasiswa</div>
        </div>
        <div class="p-5 rounded-card bg-bg-surface border border-border">
            <div class="flex items-center justify-between mb-3">
                <span class="icon-circle w-10 h-10 bg-accent-teal/15 text-accent-teal">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </span>
            </div>
            <div class="font-heading font-bold text-3xl text-text-primary tabular-nums">{{ $stats['dosen'] }}</div>
            <div class="text-sm text-text-secondary mt-1">Dosen</div>
        </div>
        <div class="p-5 rounded-card bg-bg-surface border border-border">
            <div class="flex items-center justify-between mb-3">
                <span class="icon-circle w-10 h-10 bg-accent-orange/15 text-accent-orange">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </span>
            </div>
            <div class="font-heading font-bold text-3xl text-text-primary tabular-nums">{{ $stats['ta'] }}</div>
            <div class="text-sm text-text-secondary mt-1">Data TA</div>
        </div>
        <div class="p-5 rounded-card bg-bg-surface border border-border">
            <div class="flex items-center justify-between mb-3">
                <span class="icon-circle w-10 h-10 bg-status-danger/15 text-status-danger">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
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
            <div>
                <label class="block text-xs text-text-secondary mb-1">File Excel (nama, nim, email, pembimbing1_nidn, pembimbing2_nidn)</label>
                <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="text-sm">
            </div>
            <div>
                <label class="block text-xs text-text-secondary mb-1">Pembimbing Default</label>
                <select name="pembimbing_default" class="rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                    @foreach (\App\Models\User::role('dosen')->get() as $d)
                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="px-4 py-2 rounded-xl bg-accent-blue text-white text-sm font-medium">Import</button>
            @error('file')
                <p class="text-status-danger text-xs w-full">{{ $message }}</p>
            @enderror
        </form>
    </div>

    <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-heading font-semibold text-text-primary">Data TA Terbaru</h2>
            <a href="{{ route('admin.tas') }}" class="text-sm text-accent-blue hover:underline">Kelola →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-text-secondary border-b border-border">
                        <th class="py-2 pr-4 font-medium">Mahasiswa</th>
                        <th class="py-2 pr-4 font-medium">Judul</th>
                        <th class="py-2 pr-4 font-medium">Pembimbing</th>
                        <th class="py-2 font-medium">Entri</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tas as $ta)
                        <tr class="border-b border-border last:border-0">
                            <td class="py-2.5 pr-4">{{ $ta->mahasiswa?->name }}</td>
                            <td class="py-2.5 pr-4">{{ $ta->judul_ta }}</td>
                            <td class="py-2.5 pr-4 text-xs">{{ $ta->pembimbing1?->name }}{{ $ta->pembimbing2 ? ' & ' . $ta->pembimbing2->name : '' }}</td>
                            <td class="py-2.5">{{ $ta->entries_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
