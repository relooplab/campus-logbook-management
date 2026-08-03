@extends('layouts.app')

@section('title', 'Dashboard Dosen')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading font-bold text-2xl text-text-primary">Dashboard Dosen</h1>
            <p class="text-sm text-text-secondary mt-0.5">Ringkasan aktivitas bimbingan &amp; pengujian TA</p>
        </div>
        <div class="flex flex-wrap gap-2 w-full sm:w-auto">
            <a href="{{ route('logbook.index') }}" class="flex-1 sm:flex-none px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border text-center">Semua Entri</a>
            <a href="{{ route('notifications.index') }}" class="flex-1 sm:flex-none px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border text-center">Notifikasi</a>
        </div>
    </div>

    {{-- ===== Stat cards (icon-circle + delta badge) ===== --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-5">
        <a href="{{ route('dashboard.dosen.mahasiswa-list') }}" class="p-5 rounded-card bg-bg-surface border border-border hover:border-brand/30 transition-colors">
            <div class="flex items-center justify-between mb-3">
                <span class="icon-circle w-10 h-10 bg-brand-light text-brand">
                    <span class="material-symbols-outlined icon-md">group</span>
                </span>
            </div>
            <div class="font-heading font-bold text-3xl text-text-primary tabular-nums">{{ $stats['total_bimbingan'] }}</div>
            <div class="text-sm text-text-secondary mt-1">Total Bimbingan</div>
        </a>
        <a href="{{ route('dashboard.dosen.mahasiswa-list', ['status' => 'aktif']) }}" class="p-5 rounded-card bg-bg-surface border border-border hover:border-brand/30 transition-colors">
            <div class="flex items-center justify-between mb-3">
                <span class="icon-circle w-10 h-10 bg-brand-light text-brand">
                    <span class="material-symbols-outlined icon-md">bolt</span>
                </span>
            </div>
            <div class="font-heading font-bold text-3xl text-text-primary tabular-nums">{{ $stats['sedang_progres'] }}</div>
            <div class="text-sm text-text-secondary mt-1">Sedang Progres</div>
        </a>
        <a href="{{ route('dashboard.dosen.mahasiswa-list', ['status' => 'tamat']) }}" class="p-5 rounded-card bg-bg-surface border border-border hover:border-sand/30 transition-colors">
            <div class="flex items-center justify-between mb-3">
                <span class="icon-circle w-10 h-10 bg-sand/15 text-sand">
                    <span class="material-symbols-outlined icon-md">school</span>
                </span>
            </div>
            <div class="font-heading font-bold text-3xl text-text-primary tabular-nums">{{ $stats['tamat'] }}</div>
            <div class="text-sm text-text-secondary mt-1">Tamat</div>
        </a>
        <a href="{{ route('dashboard.dosen.sidang-list') }}" class="p-5 rounded-card bg-bg-surface border border-border hover:border-brand/30 transition-colors">
            <div class="flex items-center justify-between mb-3">
                <span class="icon-circle w-10 h-10 bg-brand-light text-brand">
                    <span class="material-symbols-outlined icon-md">verified</span>
                </span>
            </div>
            <div class="font-heading font-bold text-3xl text-text-primary tabular-nums">{{ $stats['diuji'] }}</div>
            <div class="text-sm text-text-secondary mt-1">Diuji</div>
        </a>
        <a href="{{ route('quick-review.index') }}" class="p-5 rounded-card bg-bg-surface border border-border hover:border-status-danger/30 transition-colors">
            <div class="flex items-center justify-between mb-3">
                <span class="icon-circle w-10 h-10 bg-status-danger/15 text-status-danger">
                    <span class="material-symbols-outlined icon-md">fact_check</span>
                </span>
                @if ($stats['menunggu_review'] > 0)
                    <span class="flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-status-danger/15 text-status-danger">↑ {{ $stats['menunggu_review'] }} review</span>
                @endif
            </div>
            <div class="font-heading font-bold text-3xl text-text-primary tabular-nums">{{ $stats['menunggu_review'] }}</div>
            <div class="text-sm text-text-secondary mt-1">Menunggu Review</div>
        </a>
    </div>

    {{-- ===== Antrean Review ===== --}}
    <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-heading font-semibold text-text-primary">Antrean Review ({{ $queue->count() }})</h2>
            <a href="{{ route('quick-review.index') }}" class="text-sm text-brand hover:underline">Quick Review →</a>
        </div>
        @if ($queue->isEmpty())
            <p class="text-sm text-text-secondary">Tidak ada entri menunggu review.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border">
                            <th class="py-2 pr-4 font-medium">Mahasiswa</th>
                            <th class="py-2 pr-4 font-medium hidden sm:table-cell">Jenis</th>
                            <th class="py-2 pr-4 font-medium">Topik/Sesi</th>
                            <th class="py-2 pr-4 font-medium hidden md:table-cell">Dikirim</th>
                            <th class="py-2 font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($queue as $entry)
                            <tr class="border-b border-border last:border-0">
                                <td class="py-2.5 pr-4">{{ $entry->mahasiswaTa?->mahasiswa?->name }}</td>
                                <td class="py-2.5 pr-4 hidden sm:table-cell">{{ ucfirst($entry->jenis) }}</td>
                                <td class="py-2.5 pr-4">{{ $entry->jenis === 'revisi' ? 'Revisi' : 'Sesi ' . $entry->sesi_ke . ' — ' . $entry->topik }}</td>
                                <td class="py-2.5 pr-4 text-text-secondary hidden md:table-cell">{{ $entry->submitted_at?->format('d M H:i') }}</td>
                                <td class="py-2.5">
                                    <a href="{{ route('logbook.show', $entry) }}" class="px-3 py-1.5 rounded-xl bg-brand text-white text-xs font-medium hover:opacity-90">Review</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ===== Health Indicator Bimbingan ===== --}}
    <div class="card p-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <h2 class="font-heading font-semibold text-text-primary">Health Indicator Bimbingan</h2>
            <div class="flex flex-wrap gap-2 text-sm">
                <button type="button" data-health="all" class="health-filter px-3 py-1.5 rounded-full bg-bg-panel text-text-secondary hover:bg-bg-hover">Semua ({{ $perTa->count() }})</button>
                <button type="button" data-health="green" class="health-filter px-3 py-1.5 rounded-full bg-bg-panel text-text-secondary hover:bg-bg-hover"><span class="inline-block w-2 h-2 rounded-full bg-status-success mr-1"></span>{{ $healthCount['green'] }} Sehat</button>
                <button type="button" data-health="yellow" class="health-filter px-3 py-1.5 rounded-full bg-bg-panel text-text-secondary hover:bg-bg-hover"><span class="inline-block w-2 h-2 rounded-full bg-status-pending mr-1"></span>{{ $healthCount['yellow'] }} Perhatian</button>
                <button type="button" data-health="red" class="health-filter px-3 py-1.5 rounded-full bg-bg-panel text-text-secondary hover:bg-bg-hover"><span class="inline-block w-2 h-2 rounded-full bg-status-danger mr-1"></span>{{ $healthCount['red'] }} Kritis</button>
            </div>
        </div>
        @if ($perTa->isEmpty())
            <p class="text-sm text-text-secondary">Belum ada mahasiswa bimbingan.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border">
                            <th class="py-2 pr-4 font-medium">Mahasiswa</th>
                            <th class="py-2 pr-4 font-medium hidden sm:table-cell">Fase</th>
                            <th class="py-2 pr-4 font-medium hidden md:table-cell">Terakhir</th>
                            <th class="py-2 pr-4 font-medium hidden sm:table-cell">Progres</th>
                            <th class="py-2 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($perTa as $row)
                            @php $ta = $row['ta']; @endphp
                            <tr class="border-b border-border health-row last:border-0" data-health="{{ $row['regularity'] }}">
                                <td class="py-2.5 pr-4">
                                    <span class="inline-block w-3 h-3 rounded-full mr-2 align-middle {{ $row['regularity'] === 'green' ? 'bg-status-success' : '' }} {{ $row['regularity'] === 'yellow' ? 'bg-status-pending' : '' }} {{ $row['regularity'] === 'red' ? 'bg-status-danger' : '' }}" title="{{ $row['tooltip'] }}"></span>
                                    <a href="{{ route($ta->isKp() ? 'mahasiswa-kp.show' : 'mahasiswa-ta.show', $ta) }}" class="font-medium hover:text-brand align-middle">{{ $ta->mahasiswa?->name }}</a>
                                    @if ($row['warned'])
                                        <span class="material-symbols-outlined icon-sm text-status-danger align-middle" title="Sudah dikirim email inaktivitas">warning</span>
                                    @endif
                                </td>
                                <td class="py-2.5 pr-4 text-xs hidden sm:table-cell">{{ $ta->faseLabel() }}</td>
                                <td class="py-2.5 pr-4 text-xs text-text-secondary hidden md:table-cell">
                                    {{ $row['tooltip'] }}
                                    @if ($row['menunggu']) · <span class="text-status-pending">{{ $row['menunggu'] }} menunggu</span> @endif
                                </td>
                                <td class="py-2.5 pr-4 hidden sm:table-cell">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs w-16">{{ $row['approved'] }}/{{ $row['target'] }}</span>
                                        <div class="h-2 w-24 rounded-full bg-bg-panel overflow-hidden">
                                            <div class="progress-bar h-full rounded-full {{ $row['percent'] >= 100 ? 'bg-brand' : 'bg-brand' }}" style="width:{{ min(100, $row['percent']) }}%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-2.5">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium {{ $row['regularity'] === 'green' ? 'bg-status-success/10 text-status-success' : '' }} {{ $row['regularity'] === 'yellow' ? 'bg-status-pending/10 text-status-pending' : '' }} {{ $row['regularity'] === 'red' ? 'bg-status-danger/10 text-status-danger' : '' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $row['regularity'] === 'green' ? 'bg-status-success' : '' }} {{ $row['regularity'] === 'yellow' ? 'bg-status-pending' : '' }} {{ $row['regularity'] === 'red' ? 'bg-status-danger' : '' }}"></span>
                                        {{ ucfirst($row['regularity']) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ===== Manajemen Fase Mahasiswa ===== --}}
    <div class="card p-6">
        <h2 class="font-heading font-semibold text-text-primary mb-4">Manajemen Fase Mahasiswa ({{ $tas->count() }})</h2>
        @if ($tas->isEmpty())
            <p class="text-sm text-text-secondary">Belum ada mahasiswa bimbingan.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border">
                            <th class="py-2 pr-4 font-medium">Mahasiswa</th>
                            <th class="py-2 pr-4 font-medium hidden md:table-cell">{{ $tas->first()?->isKp() ? 'Tempat KP' : 'Judul TA' }}</th>
                            <th class="py-2 pr-4 font-medium hidden lg:table-cell">Pembimbing</th>
                            <th class="py-2 font-medium">Fase</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tas as $ta)
                            <tr class="border-b border-border last:border-0">
                                <td class="py-2.5 pr-4"><a href="{{ route($ta->isKp() ? 'mahasiswa-kp.show' : 'mahasiswa-ta.show', $ta) }}" class="hover:text-brand">{{ $ta->mahasiswa?->name }}</a> <span class="text-text-secondary text-xs">({{ $ta->mahasiswa?->identifier }})</span></td>
                                <td class="py-2.5 pr-4 hidden md:table-cell">{{ $ta->isKp() ? ($ta->tempat_kp ?: '—') : $ta->judul_ta }}</td>
                                <td class="py-2.5 pr-4 text-xs hidden lg:table-cell">{{ $ta->pembimbing1?->name }}{{ $ta->pembimbing2 ? ' & ' . $ta->pembimbing2->name : '' }}</td>
                                <td class="py-2.5">
                                    <form method="POST" action="{{ route($ta->isKp() ? 'mahasiswa-kp.fase' : 'mahasiswa-ta.fase', $ta) }}" class="flex flex-col sm:flex-row sm:items-center gap-1.5" onsubmit="return confirm('Ubah fase {{ $ta->jenisLabel() }} ini? Pastikan sudah benar.')">
                                        @csrf
                                        <select name="fase" class="w-full sm:w-auto rounded-lg border border-border bg-bg-surface px-2 py-1.5 text-xs">
                                            @foreach ($ta->isKp() ? \App\Models\MahasiswaTa::FASES_KP : \App\Models\MahasiswaTa::FASES as $key => $label)
                                                <option value="{{ $key }}" @selected($ta->fase === $key)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <button class="w-full sm:w-auto px-3 py-1.5 rounded-lg bg-brand text-white text-xs font-medium hover:opacity-90">Update</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Filter health indicator (klik counter untuk filter baris).
    var filters = document.querySelectorAll('.health-filter');
    var rows = document.querySelectorAll('.health-row');
    filters.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var f = btn.dataset.health;
            filters.forEach(function (b) { b.classList.remove('bg-brand', 'text-white'); b.classList.add('bg-bg-panel', 'text-text-secondary'); });
            btn.classList.remove('bg-bg-panel', 'text-text-secondary');
            btn.classList.add('bg-brand', 'text-white');
            rows.forEach(function (r) { r.style.display = (f === 'all' || r.dataset.health === f) ? '' : 'none'; });
        });
    });
</script>
@endsection
