@extends('layouts.app')

@section('title', 'Tambah Logbook')

@section('content')
@php
    $inst = \App\Models\Institution::current();
    $maxMb = $inst->maxUploadSizeMb();
    $accept = $inst->fileAccept();
    $typesLabel = strtoupper(implode(', ', $inst->allowedFileTypes()));
    $allowedTypes = $inst->allowedFileTypes();
    $userId = auth()->id();
    $programId = $ta->id ?? 0;
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
            <div id="autosave-container" class="flex items-center gap-2 mt-1">
                <p id="autosave-msg" class="text-xs text-text-secondary"></p>
                <button type="button" id="autosave-restore" class="hidden text-xs text-brand hover:underline">Pulihkan</button>
                <button type="button" id="autosave-discard" class="hidden text-xs text-status-danger hover:underline">Buang draft</button>
            </div>
        </div>
        <div>
            <label class="block text-xs text-text-secondary mb-1" for="lampiran">Lampiran ({{ $typesLabel }}, opsional, maks {{ $maxMb }} MB)</label>
            <input type="file" name="lampiran" id="lampiran" accept="{{ $accept }}" class="w-full text-sm">
            <div id="file-info" class="hidden mt-2 p-3 rounded-xl bg-bg-panel border border-border text-xs space-y-1">
                <p><span class="text-text-secondary">Nama:</span> <span id="file-name" class="font-medium text-text-primary"></span></p>
                <p><span class="text-text-secondary">Ukuran:</span> <span id="file-size" class="font-medium text-text-primary"></span></p>
                <p><span class="text-text-secondary">Tipe:</span> <span id="file-type" class="font-medium text-text-primary"></span></p>
                <p id="file-valid" class="text-status-success font-medium"></p>
                <p id="file-invalid" class="text-status-danger font-medium hidden"></p>
            </div>
            @error('lampiran')
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div class="flex flex-wrap gap-2 pt-2">
            <button type="submit" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">Simpan Draf</button>
            <button type="submit" name="submit" value="1" class="px-4 py-2 rounded-xl bg-brand text-[#0b1420] text-sm font-medium hover:opacity-90">Kirim ke dosen</button>
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
    // Key per-user & per-program agar draft TA/KP atau akun berbeda tidak tertukar.
    (function () {
        var KEY = 'lbta-draft-{{ $userId }}-{{ $programId }}-logbook';
        var topik = document.getElementById('topik');
        var progres = document.getElementById('progres_kendala');
        var tanggal = document.getElementById('tanggal_bimbingan');
        var msg = document.getElementById('autosave-msg');
        var restoreBtn = document.getElementById('autosave-restore');
        var discardBtn = document.getElementById('autosave-discard');

        function save() {
            localStorage.setItem(KEY, JSON.stringify({ topik: topik.value, progres: progres.value, tanggal: tanggal.value, ts: Date.now() }));
            msg.textContent = 'Draf tersimpan otomatis ' + new Date().toLocaleTimeString();
            restoreBtn.classList.add('hidden');
            discardBtn.classList.add('hidden');
        }

        function restoreDraft(saved) {
            if (saved.topik) topik.value = saved.topik;
            if (saved.progres) progres.value = saved.progres;
            if (saved.tanggal) tanggal.value = saved.tanggal;
            msg.textContent = 'Draf dipulihkan dari penyimpanan otomatis.';
            restoreBtn.classList.add('hidden');
            discardBtn.classList.add('hidden');
        }

        function discardDraft() {
            localStorage.removeItem(KEY);
            topik.value = '';
            progres.value = '';
            tanggal.value = '{{ now()->format('Y-m-d') }}';
            msg.textContent = 'Draft dibuang.';
            restoreBtn.classList.add('hidden');
            discardBtn.classList.add('hidden');
        }

        // Cek draft tersimpan.
        try {
            var saved = JSON.parse(localStorage.getItem(KEY) || 'null');
            if (saved && saved.progres && !progres.value) {
                msg.textContent = 'Draft tersimpan ditemukan (' + new Date(saved.ts).toLocaleTimeString() + ').';
                restoreBtn.classList.remove('hidden');
                discardBtn.classList.remove('hidden');
            }
        } catch (e) {}

        restoreBtn.addEventListener('click', function () {
            try {
                var saved = JSON.parse(localStorage.getItem(KEY) || 'null');
                if (saved) restoreDraft(saved);
            } catch (e) {}
        });
        discardBtn.addEventListener('click', discardDraft);

        setInterval(save, 5000);
        // Hapus draf saat berhasil submit.
        document.getElementById('logbook-form').addEventListener('submit', function () {
            localStorage.removeItem(KEY);
        });
    })();
</script>
<script>
    // ---- Upload feedback: nama, ukuran, tipe, validasi sebelum submit ----
    (function () {
        var fileInput = document.getElementById('lampiran');
        var infoBox = document.getElementById('file-info');
        var nameEl = document.getElementById('file-name');
        var sizeEl = document.getElementById('file-size');
        var typeEl = document.getElementById('file-type');
        var validEl = document.getElementById('file-valid');
        var invalidEl = document.getElementById('file-invalid');
        var maxMb = {{ $maxMb }};
        var allowedTypes = @json($allowedTypes);

        function formatBytes(bytes) {
            if (bytes <= 0) return '0 B';
            var units = ['B', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(1024));
            return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + units[i];
        }

        fileInput.addEventListener('change', function () {
            var file = fileInput.files[0];
            if (!file) {
                infoBox.classList.add('hidden');
                return;
            }
            infoBox.classList.remove('hidden');
            nameEl.textContent = file.name;
            sizeEl.textContent = formatBytes(file.size);
            typeEl.textContent = file.type || 'Tidak diketahui';

            var ext = (file.name.split('.').pop() || '').toLowerCase();
            var sizeOk = file.size <= maxMb * 1024 * 1024;
            var typeOk = allowedTypes.includes(ext);

            if (sizeOk && typeOk) {
                validEl.textContent = '✓ File valid. Siap diunggah.';
                validEl.classList.remove('hidden');
                invalidEl.classList.add('hidden');
            } else {
                validEl.classList.add('hidden');
                invalidEl.classList.remove('hidden');
                var reasons = [];
                if (!sizeOk) reasons.push('Ukuran melebihi batas ' + maxMb + ' MB');
                if (!typeOk) reasons.push('Format .' + ext + ' tidak diizinkan (hanya: ' + allowedTypes.join(', ') + ')');
                invalidEl.textContent = '✗ ' + reasons.join('. ') + '.';
            }
        });
    })();
</script>
@endsection