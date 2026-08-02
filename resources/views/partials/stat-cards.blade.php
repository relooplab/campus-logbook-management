<div class="grid grid-cols-2 gap-3 text-sm">
    <div class="p-4 rounded-xl bg-bg-panel">
        <div class="flex items-center justify-between mb-2">
            <span class="icon-circle w-8 h-8 bg-accent-teal/15 text-accent-teal">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </span>
        </div>
        <div class="font-heading font-bold text-2xl text-text-primary tabular-nums">{{ $stats['streak'] }}<span class="text-sm font-medium text-text-secondary"> minggu</span></div>
        <div class="text-xs text-text-secondary mt-1">Streak konsistensi</div>
    </div>
    <div class="p-4 rounded-xl bg-bg-panel">
        <div class="flex items-center justify-between mb-2">
            <span class="icon-circle w-8 h-8 bg-accent-blue/15 text-accent-blue">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </span>
        </div>
        <div class="font-heading font-bold text-2xl text-text-primary tabular-nums">{{ $stats['ratioRevisi'] }}<span class="text-sm font-medium text-text-secondary">%</span></div>
        <div class="text-xs text-text-secondary mt-1">Rasio revisi</div>
    </div>
    <div class="p-4 rounded-xl bg-bg-panel">
        <div class="flex items-center justify-between mb-2">
            <span class="icon-circle w-8 h-8 bg-accent-orange/15 text-accent-orange">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
        </div>
        <div class="font-heading font-bold text-2xl text-text-primary tabular-nums">{{ $stats['avgWait'] ?? '—' }}</div>
        <div class="text-xs text-text-secondary mt-1">Rata-rata tunggu review (hari)</div>
    </div>
    <div class="p-4 rounded-xl bg-bg-panel">
        <div class="flex items-center justify-between mb-2">
            <span class="icon-circle w-8 h-8 bg-accent-purple/15 text-accent-purple">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/></svg>
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
