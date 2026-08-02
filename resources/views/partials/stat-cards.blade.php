<div class="grid grid-cols-2 gap-3 text-sm">
    <div class="p-4 rounded-xl bg-bg-panel">
        <div class="flex items-center justify-between mb-2">
            <span class="icon-circle w-8 h-8 bg-brand-light text-brand">
                <span class="material-symbols-outlined icon-sm">bolt</span>
            </span>
        </div>
        <div class="font-heading font-bold text-2xl text-text-primary tabular-nums">{{ $stats['streak'] }}<span class="text-sm font-medium text-text-secondary"> minggu</span></div>
        <div class="text-xs text-text-secondary mt-1">Streak konsistensi</div>
    </div>
    <div class="p-4 rounded-xl bg-bg-panel">
        <div class="flex items-center justify-between mb-2">
            <span class="icon-circle w-8 h-8 bg-brand-light text-brand">
                <span class="material-symbols-outlined icon-sm">bar_chart</span>
            </span>
        </div>
        <div class="font-heading font-bold text-2xl text-text-primary tabular-nums">{{ $stats['ratioRevisi'] }}<span class="text-sm font-medium text-text-secondary">%</span></div>
        <div class="text-xs text-text-secondary mt-1">Rasio revisi</div>
    </div>
    <div class="p-4 rounded-xl bg-bg-panel">
        <div class="flex items-center justify-between mb-2">
            <span class="icon-circle w-8 h-8 bg-sand/15 text-sand">
                <span class="material-symbols-outlined icon-sm">schedule</span>
            </span>
        </div>
        <div class="font-heading font-bold text-2xl text-text-primary tabular-nums">{{ $stats['avgWait'] ?? '—' }}</div>
        <div class="text-xs text-text-secondary mt-1">Rata-rata tunggu review (hari)</div>
    </div>
    <div class="p-4 rounded-xl bg-bg-panel">
        <div class="flex items-center justify-between mb-2">
            <span class="icon-circle w-8 h-8 bg-brand-light text-brand">
                <span class="material-symbols-outlined icon-sm">forum</span>
            </span>
        </div>
        <div class="font-heading font-bold text-2xl text-text-primary tabular-nums">{{ $stats['avgResponse'] ?? '—' }}</div>
        <div class="text-xs text-text-secondary mt-1">Rata-rata respons revisi (hari)</div>
    </div>
</div>
<div class="mt-3 pt-3 border-t border-border">
    <div class="flex flex-wrap gap-2 text-xs">
        <span class="px-2 py-1 rounded-full bg-bg-hover text-text-primary">Draf: <b class="tabular-nums">{{ $stats['draft'] }}</b></span>
        <span class="px-2 py-1 rounded-full bg-status-pending/10 text-status-pending">Dikirim: <b class="tabular-nums">{{ $stats['submitted']->count() }}</b></span>
        <span class="px-2 py-1 rounded-full bg-status-danger/10 text-status-danger">Revisi: <b class="tabular-nums">{{ $stats['revisi'] }}</b></span>
    </div>
</div>
