@extends('layouts.app')

@section('title', 'Tambah Catatan Harian')

@section('content')
<div class="max-w-2xl">
    <div class="flex items-center justify-between mb-5">
        <h1 class="font-heading font-bold text-2xl text-text-primary">Tambah Catatan Harian KP</h1>
        <a href="{{ route('logbook-harian.index', $mahasiswaTa) }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">← Kembali</a>
    </div>
    <form method="POST" action="{{ route('logbook-harian.store', $mahasiswaTa) }}" enctype="multipart/form-data"
        class="card p-6 space-y-4">
        @csrf
        <div>
            <label class="block text-xs text-text-secondary mb-1" for="tanggal">Tanggal Kegiatan</label>
            <input type="date" name="tanggal" id="tanggal" required value="{{ old('tanggal', now()->format('Y-m-d')) }}"
                class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
            @error('tanggal')
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="block text-xs text-text-secondary mb-1" for="kegiatan">Kegiatan Lapangan</label>
            <textarea name="kegiatan" id="kegiatan" rows="5" required
                placeholder="Laporan kegiatan lapangan singkat yang dilakukan hari ini..."
                class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">{{ old('kegiatan') }}</textarea>
            @error('kegiatan')
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="block text-xs text-text-secondary mb-1" for="kendala">Kendala <span class="text-text-secondary">(opsional)</span></label>
            <textarea name="kendala" id="kendala" rows="3"
                placeholder="Kendala yang dihadapi pada kegiatan hari ini..."
                class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">{{ old('kendala') }}</textarea>
            @error('kendala')
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="block text-xs text-text-secondary mb-1">Foto Kegiatan <span class="text-text-secondary">(opsional, maks 2 foto)</span></label>
            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-text-secondary mb-1" for="foto_1">Foto 1</label>
                    <input type="file" name="foto_1" id="foto_1" accept="image/*" class="w-full text-sm">
                    @error('foto_1')
                        <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs text-text-secondary mb-1" for="foto_2">Foto 2</label>
                    <input type="file" name="foto_2" id="foto_2" accept="image/*" class="w-full text-sm">
                    @error('foto_2')
                        <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>
        <div class="flex flex-wrap gap-2 pt-2">
            <button type="submit" class="px-4 py-2 rounded-xl bg-brand text-white text-sm font-medium hover:opacity-90">Simpan</button>
            <a href="{{ route('logbook-harian.index', $mahasiswaTa) }}" class="px-4 py-2 rounded-xl bg-status-danger/10 text-status-danger text-sm font-medium hover:bg-status-danger/20">Batal</a>
        </div>
    </form>
</div>
@endsection