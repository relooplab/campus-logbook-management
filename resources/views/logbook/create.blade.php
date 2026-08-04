@extends('layouts.app')

@section('title', 'Tambah Logbook')

@section('content')
@php
    $inst = \App\Models\Institution::active();
    $maxMb = $inst->maxUploadSizeMb();
    $accept = $inst->fileAccept();
    $typesLabel = strtoupper(implode(', ', $inst->allowedFileTypes()));
@endphp
<div class="max-w-2xl">
    <div class="flex items-center justify-between mb-5">
        <h1 class="font-heading font-bold text-2xl text-text-primary">Tambah Entri Logbook</h1>
        <a href="{{ route('logbook.index') }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">← Kembali</a>
    </div>
    <form method="POST" action="{{ route('logbook.store') }}" enctype="multipart/form-data"
        class="card p-6 space-y-4" id="logbook-form">
        @csrf
        {{-- Auto-fill sesi berikutnya (readonly) --}}
        <div>
            <label class="block text-xs text-text-secondary mb-1">Sesi Ke</label>
            <input type="text" value="Sesi {{ $nextSesi }}" readonly disabled
                class="w-full rounded-xl border border-border bg-bg-panel px-3.5 py-2 text-sm text-text-secondary">
        </div>
        <div>
            <label class="block text-xs text-text-secondary mb-1" for="tanggal_bimbingan">Tanggal Bimbingan</label>
            <input type="date" name="tanggal_bimbingan" id="tanggal_bimbingan" required
                value="{{ old('tanggal_bimbingan', now()->format('Y-m-d')) }}"
                class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
            @error('tanggal_bimbingan')
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="block text-xs text-text-secondary mb-1" for="topik">Topik Bimbingan</label>
            <input type="text" name="topik" id="topik" required value="{{ old('topik', $lastTopik) }}"
                placeholder="{{ $lastTopik ? 'Auto: ' . $lastTopik : '' }}"
                class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
            @if ($lastTopik)
                <p class="text-xs text-text-secondary mt-1">Topik sebelumnya: {{ $lastTopik }}</p>
            @endif
            @error('topik')
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="block text-xs text-text-secondary mb-1" for="progres_kendala">Ringkasan Perbaikan</label>
            <div class="flex gap-1 mb-2" id="tb-toolbar">
                <button type="button" data-insert="bullet" class="px-3 py-1 rounded bg-bg-panel hover:bg-bg-hover text-xs">• Bullet</button>
                <button type="button" data-insert="number" class="px-3 py-1 rounded bg-bg-panel hover:bg-bg-hover text-xs">1. Number</button>
                <button type="button" data-insert="dash" class="px-3 py-1 rounded bg-bg-panel hover:bg-bg-hover text-xs">— Dash</button>
            </div>
            <textarea name="progres_kendala" id="progres_kendala" rows="6" required
                class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">{{ old('progres_kendala') }}</textarea>
            @error('progres_kendala')
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
            <p id="autosave-msg" class="text-xs text-text-secondary mt-1"></p>
        </div>
        <div>
            <label class="block text-xs text-text-secondary mb-1" for="lampiran">Lampiran ({{ $typesLabel }}, opsional, maks {{ $maxMb }} MB)</label>
            <input type="file" name="lampiran" id="lampiran" accept="{{ $accept }}" class="w-full text-sm">
            @error('lampiran')
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div class="flex flex-wrap gap-2 pt-2">
            <button type="submit" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">Simpan Draf</button>
            <button type="submit" name="submit" value="1" class="px-4 py-2 rounded-xl bg-brand text-white text-sm font-medium hover:opacity-90">Kirim ke dosen</button>
            <a href="{{ route('logbook.index') }}" class="px-4 py-2 rounded-xl bg-status-danger/10 text-status-danger text-sm font-medium hover:bg-status-danger/20">Batal</a>
        </div>
    </form>
</div>
@endsection

@section('scripts')
@include('partials.tb-script')
<script>
    initTbToolbar('progres_kendala');
</script>
<script>
    // Auto-save draft ke localStorage (tiap 5 detik) + restore.
    (function () {
        var KEY = 'lbta-logbook-draft';
        var topik = document.getElementById('topik');
        var progres = document.getElementById('progres_kendala');
        var tanggal = document.getElementById('tanggal_bimbingan');
        var msg = document.getElementById('autosave-msg');
        function save() {
            localStorage.setItem(KEY, JSON.stringify({ topik: topik.value, progres: progres.value, tanggal: tanggal.value, ts: Date.now() }));
            msg.textContent = 'Draf tersimpan otomatis ' + new Date().toLocaleTimeString();
        }
        // Restore draf (hanya jika ada dan tidak sedang mengisi ulang).
        try {
            var saved = JSON.parse(localStorage.getItem(KEY) || 'null');
            if (saved && saved.progres && !progres.value) {
                if (saved.topik) topik.value = saved.topik;
                if (saved.progres) progres.value = saved.progres;
                if (saved.tanggal) tanggal.value = saved.tanggal;
                msg.textContent = 'Draf dipulihkan dari penyimpanan otomatis.';
            }
        } catch (e) {}
        setInterval(save, 5000);
        // Hapus draf saat berhasil submit.
        document.getElementById('logbook-form').addEventListener('submit', function () {
            localStorage.removeItem(KEY);
        });
    })();
</script>
@endsection