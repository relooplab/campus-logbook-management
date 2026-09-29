<aside class="academic-workspace-panel min-w-0 space-y-5" aria-label="Konteks akademik">
    <section class="card p-5" aria-labelledby="academic-summary-title">
        <h2 id="academic-summary-title" class="flex items-center gap-2 font-heading font-semibold"><span class="material-symbols-outlined icon-md text-brand" aria-hidden="true">analytics</span> Ringkasan Akademik</h2>
        <p class="mt-1 text-sm text-text-secondary">Informasi penting program Anda.</p>
        <dl class="mt-4 divide-y divide-border rounded-xl border border-border bg-bg-panel px-3">
            @foreach (array_filter([
                'NIM' => auth()->user()->nim,
                'Program aktif' => collect([$ta, $kp])->filter(fn ($program) => $program && $program->status_ta === 'aktif')->map(fn ($program) => strtoupper($program->jenis))->implode(' & '),
                'Fase TA' => $ta && $ta->status_ta === 'aktif' ? $ta->faseLabel() : null,
                'Pembimbing TA' => $ta && $ta->status_ta === 'aktif' ? collect([$ta->pembimbing1?->name, $ta->pembimbing2?->name])->filter()->implode(' · ') : null,
                'Penguji TA' => $ta && $ta->status_ta === 'aktif' ? collect([$ta->penguji1?->name, $ta->penguji2?->name])->filter()->implode(' · ') : null,
                'Pembimbing KP' => $kp && $kp->status_ta === 'aktif' ? collect([$kp->pembimbing1?->name, $kp->pembimbing2?->name])->filter()->implode(' · ') : null,
                'Penguji KP' => $kp && $kp->status_ta === 'aktif' ? collect([$kp->penguji1?->name, $kp->penguji2?->name])->filter()->implode(' · ') : null,
            ]) as $label => $value)
                <div class="flex min-w-0 flex-wrap items-start justify-between gap-x-3 gap-y-1 py-3 text-sm"><dt class="text-text-secondary">{{ $label }}</dt><dd class="min-w-0 max-w-full break-words font-medium text-text-primary">{{ $value }}</dd></div>
            @endforeach
        </dl>
    </section>
    <section class="card p-5" aria-labelledby="quick-actions-title">
        <h2 id="quick-actions-title" class="flex items-center gap-2 font-heading font-semibold"><span class="material-symbols-outlined icon-md text-brand" aria-hidden="true">bolt</span> Quick Actions</h2>
        <p class="mt-1 text-sm text-text-secondary">Akses cepat fitur yang sering digunakan.</p>
        <nav class="mt-4 space-y-2" aria-label="Aksi cepat akademik">
            @foreach ([['logbook.create', 'menu_book', 'Tambah Logbook'], ['scheduling.index', 'calendar_month', 'Jadwalkan Bimbingan'], ['logbook.feedback', 'forum', 'Riwayat Umpan Balik']] as [$routeName, $icon, $label])
                <a href="{{ route($routeName) }}" class="flex min-w-0 items-center gap-3 rounded-xl border border-border bg-bg-panel px-3 py-3 text-sm font-medium text-text-primary hover:border-brand/40 hover:bg-bg-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand"><span class="material-symbols-outlined icon-md shrink-0 text-brand" aria-hidden="true">{{ $icon }}</span><span class="min-w-0 flex-1">{{ $label }}</span><span class="material-symbols-outlined icon-sm shrink-0 text-text-secondary" aria-hidden="true">chevron_right</span></a>
            @endforeach
        </nav>
    </section>
    <div class="rounded-card border border-status-info/30 bg-status-info/10 p-5 text-sm text-text-primary"><p class="flex items-center gap-2 font-semibold"><span class="material-symbols-outlined icon-md text-status-info" aria-hidden="true">info</span> Informasi</p><p class="mt-2 text-text-secondary">Usulan penguji untuk program aktif perlu persetujuan semua dosen terkait sebelum ditetapkan.</p></div>
</aside>