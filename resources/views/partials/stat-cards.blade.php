<div class="grid grid-cols-2 gap-3 text-sm">
    <div class="p-4 rounded-card bg-bg-panel border border-border @if(isset($accentCtx)){{ $accentCtx }}@endif">
        <div class="flex items-center justify-between mb-2">
            <span class="icon-chip w-8 h-8">
                <span class="material-symbols-outlined icon-sm text-accent-orange">bolt</span>
            </span>
        </div>
        <div class="font-mono font-bold text-[32px] leading-tight text-text-primary tabular-nums">{{ $stats['streak'] }}<span class="text-sm font-medium text-text-secondary"> minggu</span></div>
        <div class="text-xs text-text-secondary mt-1">Streak konsistensi</div>
    </div>
    <div class="p-4 rounded-card bg-bg-panel border border-border">
        <div class="flex items-center justify-between mb-2">
            <span class="icon-chip w-8 h-8">
                <span class="material-symbols-outlined icon-sm text-accent-blue">bar_chart</span>
            </span>
        </div>
        <div class="font-mono font-bold text-[32px] leading-tight text-text-primary tabular-nums">{{ $stats['ratioRevisi'] }}<span class="text-sm font-medium text-text-secondary">%</span></div>
        <div class="text-xs text-text-secondary mt-1">Rasio revisi</div>
    </div>
    <div class="p-4 rounded-card bg-bg-panel border border-border">
        <div class="flex items-center justify-between mb-2">
            <span class="icon-chip w-8 h-8">
                <span class="material-symbols-outlined icon-sm text-status-pending">schedule</span>
            </span>
        </div>
        <div class="font-mono font-bold text-[32px] leading-tight text-text-primary tabular-nums">{{ $stats['avgWait'] ?? '—' }}</div>
        <div class="text-xs text-text-secondary mt-1">Rata-rata tunggu review (hari)</div>
    </div>
    <div class="p-4 rounded-card bg-bg-panel border border-border">
        <div class="flex items-center justify-between mb-2">
            <span class="icon-chip w-8 h-8">
                <span class="material-symbols-outlined icon-sm text-accent-teal">forum</span>
            </span>
        </div>
        <div class="font-mono font-bold text-[32px] leading-tight text-text-primary tabular-nums">{{ $stats['avgResponse'] ?? '—' }}</div>
        <div class="text-xs text-text-secondary mt-1">Rata-rata respons revisi (hari)</div>
        <div class="mt-2">
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-status-success/10 text-status-success text-[10px] font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-current"></span>Baik</span>
        </div>
    </div>
</div>
<div class="mt-3 pt-3 border-t border-border">
    <div class="flex flex-wrap gap-2 text-xs">
        <span class="px-2 py-1 rounded-full bg-bg-hover text-text-primary">Draf: <b class="font-mono tabular-nums">{{ $stats['draft'] }}</b></span>
        <span class="px-2 py-1 rounded-full bg-status-pending/10 text-status-pending">Dikirim: <b class="font-mono tabular-nums">{{ $stats['submitted']->count() }}</b></span>
        <span class="px-2 py-1 rounded-full bg-status-danger/10 text-status-danger">Revisi: <b class="font-mono tabular-nums">{{ $stats['revisi'] }}</b></span>
    </div>
</div>
