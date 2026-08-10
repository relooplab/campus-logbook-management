@extends('layouts.app')

@section('title', 'Edit Catatan Harian')

@section('content')
<div class="max-w-2xl">
    <div class="flex items-center justify-between mb-5">
        <h1 class="font-heading font-bold text-2xl text-text-primary">Edit Catatan Harian KP</h1>
        <a href="{{ route('logbook-harian.index', $mahasiswaTa) }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">← Kembali</a>
    </div>
    <form method="POST" action="{{ route('logbook-harian.update', [$mahasiswaTa, $logbookHarian]) }}" enctype="multipart/form-data"
        class="card p-6 space-y-4">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-xs text-text-secondary mb-1" for="tanggal">Tanggal Kegiatan</label>
            <input type="date" name="tanggal" id="tanggal" required value="{{ old('tanggal', $logbookHarian->tanggal->format('Y-m-d')) }}"
                min="{{ $mahasiswaTa->periode_mulai?->format('Y-m-d') }}"
                max="{{ $mahasiswaTa->periode_selesai?->format('Y-m-d') }}"
                class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
            @if ($mahasiswaTa->periode_mulai)
                <p class="text-xs text-text-secondary mt-1">Periode KP: {{ $mahasiswaTa->periode_mulai->format('d M Y') }} – {{ $mahasiswaTa->periode_selesai?->format('d M Y') ?? 'sekarang' }}</p>
            @endif
            @error('tanggal')
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="block text-xs text-text-secondary mb-1" for="kegiatan">Kegiatan Lapangan</label>
            <textarea name="kegiatan" id="kegiatan" rows="5" required
                class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">{{ old('kegiatan', $logbookHarian->kegiatan) }}</textarea>
            @error('kegiatan')
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="block text-xs text-text-secondary mb-1" for="kendala">Kendala <span class="text-text-secondary">(opsional)</span></label>
            <textarea name="kendala" id="kendala" rows="3"
                class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">{{ old('kendala', $logbookHarian->kendala) }}</textarea>
            @error('kendala')
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="block text-xs text-text-secondary mb-1">Foto Kegiatan <span class="text-text-secondary">(opsional, maks 2 foto)</span></label>
            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-text-secondary mb-1" for="foto_1">Foto 1</label>
                    @if ($logbookHarian->foto_1)
                        <img src="{{ route('logbook-harian.foto', [$mahasiswaTa, $logbookHarian, 1]) }}"
                            alt="Foto 1" class="w-full h-32 object-cover rounded-xl mb-2">
                    @endif
                    <input type="file" name="foto_1" id="foto_1" accept="image/*" class="w-full text-sm">
                    @error('foto_1')
                        <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs text-text-secondary mb-1" for="foto_2">Foto 2</label>
                    @if ($logbookHarian->foto_2)
                        <img src="{{ route('logbook-harian.foto', [$mahasiswaTa, $logbookHarian, 2]) }}"
                            alt="Foto 2" class="w-full h-32 object-cover rounded-xl mb-2">
                    @endif
                    <input type="file" name="foto_2" id="foto_2" accept="image/*" class="w-full text-sm">
                    @error('foto_2')
                        <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>
        <div class="flex flex-wrap gap-2 pt-2">
            <button type="submit" class="px-4 py-2 rounded-xl bg-brand text-[#0b1420] text-sm font-medium hover:opacity-90">Simpan</button>
            <a href="{{ route('logbook-harian.index', $mahasiswaTa) }}" class="px-4 py-2 rounded-xl bg-status-danger/10 text-status-danger text-sm font-medium hover:bg-status-danger/20">Batal</a>
        </div>
    </form>
</div>
@endsection