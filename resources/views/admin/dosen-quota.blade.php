@extends('layouts.app')

@section('title', 'Kuota Dosen')

@section('content')
<div class="max-w-2xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading font-bold text-2xl text-text-primary">Kuota Dosen</h1>
            <p class="text-sm text-text-secondary mt-0.5">{{ $user->name }} · {{ $user->email }}</p>
        </div>
        <a href="{{ route('admin.users') }}" class="px-4 py-2 rounded-control bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">← Daftar Pengguna</a>
    </div>

    <div class="card p-6 space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="font-heading font-semibold text-text-primary">Kuota Penyimpanan Dos&ntilde; Di Institusi</h2>
            <span class="badge badge-info">Dosen</span>
        </div>

        <p class="text-sm text-text-secondary">Batas penyimpanan per dosen <strong>dalam pool institusi</strong>. Efektif = min(pool institusi, batas per-user). Kosongkan untuk unlimited dalam pool.</p>

        @if ($poolMb > 0)
            <div class="px-4 py-3 rounded-xl bg-bg-panel border border-border text-sm">
                <span class="text-text-secondary">Pool kuota institusi saat ini:</span>
                <span class="font-semibold text-text-primary">{{ number_format($poolMb / 1024, 2) }} GB</span>
                <span class="text-text-secondary">({{ $poolMb }} MB)</span>
            </div>
        @else
            <div class="px-4 py-3 rounded-xl bg-status-pending/10 border border-status-pending/30 text-sm text-status-pending">
                Institusi ini tidak memiliki langganan direktori aktif / pool kuota. Kuota per-user tidak efektif.
            </div>
        @endif

        <form method="POST" action="{{ route('admin.dosen.kuota.update', $user) }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs text-text-secondary mb-1">Kuota Per-User dalam Pool (MB)</label>
                <input type="number" name="institution_storage_limit_mb" value="{{ $user->institution_storage_limit_mb ?? '' }}" min="0" placeholder="Kosongkan = unlimited dalam pool"
                    class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                @error('institution_storage_limit_mb') <p class="text-status-danger text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="px-4 py-2 rounded-control bg-brand text-[#0b1420] text-sm font-semibold hover:bg-brand-hover transition-colors">Simpan Kuota</button>
                <a href="{{ route('admin.users') }}" class="px-4 py-2 rounded-control bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
