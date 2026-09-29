{{--
    Kartu status penyimpanan otomatis (dipakai halaman logbook & revisi).
    Presentasi saja: logika autosave tetap di masing-masing halaman, nilai
    yang ditampilkan berasal dari hasil operasi localStorage yang sama.
--}}
@props([
    'panel' => 'autosave',
    'title' => 'Penyimpanan Otomatis',
    'caption' => 'Draf disimpan berkala di perangkat ini sebelum entri dikirim.',
])

<div class="card form-workspace-card p-5" data-autosave-panel="{{ $panel }}">
    <div class="form-card-head">
        <span class="icon-chip h-10 w-10" aria-hidden="true">
            <span class="material-symbols-outlined icon-md text-brand" data-autosave-icon>save</span>
        </span>
        <div class="min-w-0">
            <p class="text-h2 text-text-primary">{{ $title }}</p>
            <p class="text-caption text-text-secondary">{{ $caption }}</p>
        </div>
    </div>
    <div class="mt-4" role="status" aria-live="polite">
        <p class="text-sm font-medium text-text-secondary" data-autosave-state>Belum ada perubahan</p>
        <p class="text-caption text-text-secondary" data-autosave-time></p>
    </div>
    <div class="mt-3 flex flex-wrap items-center gap-2">
        <button type="button" class="btn-ghost hidden px-3 py-1.5 text-xs" data-autosave-retry>Coba lagi</button>
        <button type="button" class="btn-secondary hidden px-3 py-1.5 text-xs" data-autosave-restore>Pulihkan draf</button>
        <button type="button" class="hidden px-1 py-1.5 text-xs text-status-danger hover:underline" data-autosave-discard>Buang draf</button>
    </div>
</div>
