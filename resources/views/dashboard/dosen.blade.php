@extends('layouts.app')

@section('title', 'Dashboard Dosen')

@section('content')
<div class="space-y-6">
@php $actionLinks = '
    <a href="' . route('approval.index') . '" class="flex-1 sm:flex-none px-4 py-2 rounded-xl bg-brand text-[#0b1420] text-sm font-medium hover:opacity-90 inline-flex items-center justify-center gap-1.5">
        <span class="material-symbols-outlined icon-sm text-accent-teal">person_add</span> Tambah Mahasiswa
    </a>
    <a href="' . route('dosen-sidang.index') . '" class="flex-1 sm:flex-none px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border inline-flex items-center justify-center gap-1.5">
        <span class="material-symbols-outlined icon-sm text-accent-purple">verified</span> Catat Sidang
    </a>'; @endphp
<x-page-header subtitle="Bimbingan & Pengujian" title="Dashboard Dosen">
    <x-slot:actions>{!! $actionLinks !!}</x-slot:actions>
</x-page-header>

    {{-- ===== Ringkasan Aksi Hari Ini ===== --}}
    <div class="card p-6">
        <div class="flex items-center gap-3 mb-4">
            <span class="icon-circle w-10 h-10 bg-brand-light text-brand">
                <span class="material-symbols-outlined icon-md text-status-info">today</span>
            </span>
            <div>
                <h2 class="font-heading font-semibold text-text-primary">Aksi Hari Ini</h2>
                <p class="text-sm text-text-secondary">Prioritas yang perlu Anda tindak lanjuti</p>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            {{-- Menunggu persetujuan (paling penting) --}}
            <a href="{{ route('approval.index') }}" class="relative flex items-center gap-3 p-4 rounded-xl bg-status-pending/15 border border-status-pending/40 shadow-lg shadow-status-pending/10 hover:border-status-pending/60 transition-colors">
                @if ($pendingRegistrations > 0)
                    <span class="absolute top-3 right-3 flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-status-pending opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-status-pending"></span>
                    </span>
                @endif
                <span class="icon-circle w-10 h-10 bg-status-pending/20 text-status-pending animate-pulse">
                    <span class="material-symbols-outlined icon-md text-accent-teal">person_add</span>
                </span>
                <div>
                    <div class="font-heading font-bold text-2xl text-text-primary tabular-nums">{{ $pendingRegistrations }}</div>
                    <div class="text-sm font-medium text-status-pending">Menunggu persetujuan</div>
                </div>
                @if ($pendingRegistrations > 0)
                    <span class="ml-auto text-status-pending text-xs font-semibold">Setujui →</span>
                @endif
            </a>

            {{-- Entri menunggu review --}}
            <a href="{{ route('quick-review.index') }}" class="flex items-center gap-3 p-4 rounded-xl bg-status-danger/10 border border-status-danger/30 hover:border-status-danger/50 transition-colors">
                <span class="icon-circle w-10 h-10 bg-status-danger/15 text-status-danger">
                    <span class="material-symbols-outlined icon-md text-accent-blue">fact_check</span>
                </span>
                <div>
                    <div class="font-heading font-bold text-2xl text-text-primary tabular-nums">{{ $stats['menunggu_review'] }}</div>
                    <div class="text-sm text-text-secondary">Entri menunggu review</div>
                </div>
                @if ($stats['menunggu_review'] > 0)
                    <span class="ml-auto text-status-danger text-xs font-medium">Review →</span>
                @endif
            </a>

            {{-- Mahasiswa perlu perhatian --}}
            <a href="{{ route('dashboard.dosen.mahasiswa-list') }}" class="flex items-center gap-3 p-4 rounded-xl bg-status-info/10 border border-status-info/30 hover:border-status-info/50 transition-colors">
                <span class="icon-circle w-10 h-10 bg-status-info/15 text-status-info">
                    <span class="material-symbols-outlined icon-md text-status-danger">monitor_heart</span>
                </span>
                <div>
                    <div class="font-heading font-bold text-2xl text-text-primary tabular-nums">{{ $needsAttention }}</div>
                    <div class="text-sm text-text-secondary">Mahasiswa perlu perhatian</div>
                </div>
                @if ($needsAttention > 0)
                    <span class="ml-auto text-status-info text-xs font-medium">Lihat →</span>
                @endif
            </a>
        </div>
    </div>

    {{-- ===== Mahasiswa Sekali Pandang (butuh perhatian dulu) ===== --}}
    @if ($perTa->isNotEmpty())
        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="font-heading font-semibold text-text-primary">Mahasiswa Sekali Pandang</h2>
                    <p class="text-sm text-text-secondary mt-0.5">Status bimbingan tiap mahasiswa, yang butuh perhatian tampil lebih dulu</p>
                </div>
                <div class="hidden sm:flex items-center gap-3 text-xs text-text-secondary">
                    <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-status-danger"></span> Kritis</span>
                    <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-status-pending"></span> Perhatian</span>
                    <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-status-success"></span> Sehat</span>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                @foreach ($perTa as $row)
                    @php
                        $ta = $row['ta'];
                        $hl = $row['regularity'] === 'red' ? 'border-l-status-danger' : ($row['regularity'] === 'yellow' ? 'border-l-status-pending' : 'border-l-status-success');
                        $hc = $row['regularity'] === 'red' ? 'text-status-danger' : ($row['regularity'] === 'yellow' ? 'text-status-pending' : 'text-status-success');
                        $route = $ta->isKp() ? 'mahasiswa-kp.show' : 'mahasiswa-ta.show';
                    @endphp
                    <a href="{{ route($route, $ta) }}"
                        class="block p-4 rounded-xl bg-bg-panel border border-border border-l-4 {{ $hl }} hover:border-brand/30 hover:bg-bg-surface transition-colors">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <span class="font-medium text-text-primary truncate">{{ $ta->mahasiswa?->name }}</span>
                            <span class="text-xs font-medium {{ $hc }}">{{ ucfirst($row['regularity']) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-2 mb-1.5">
                            <span class="text-xs text-text-secondary">{{ $ta->faseLabel() }}</span>
                            @if ($row['menunggu'] > 0)
                                <span class="text-xs font-semibold text-status-pending">{{ $row['menunggu'] }} menunggu</span>
                            @endif
                        </div>
                        <div class="h-1.5 rounded-full bg-bg-hover overflow-hidden">
                            <div class="h-full rounded-full bg-brand" style="width: {{ min(100, $row['percent']) }}%"></div>
                        </div>
                        <div class="mt-1 text-[10px] text-text-secondary tabular-nums">{{ $row['approved'] }}/{{ $row['target'] }} sesi</div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ===== Stat cards (icon-circle + delta badge) ===== --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-5">
        <a href="{{ route('dashboard.dosen.mahasiswa-list') }}" class="p-5 rounded-card bg-bg-surface border border-border hover:border-brand/30 transition-colors">
            <div class="flex items-center justify-between mb-3">
                <span class="icon-circle w-10 h-10 bg-brand-light text-brand">
                    <span class="material-symbols-outlined icon-md text-accent-blue">group</span>
                </span>
            </div>
            <div class="font-heading font-bold text-3xl text-text-primary tabular-nums">{{ $stats['total_bimbingan'] }}</div>
            <div class="text-sm text-text-secondary mt-1">Total Bimbingan</div>
        </a>
        <a href="{{ route('dashboard.dosen.mahasiswa-list', ['status' => 'aktif']) }}" class="p-5 rounded-card bg-bg-surface border border-border hover:border-brand/30 transition-colors">
            <div class="flex items-center justify-between mb-3">
                <span class="icon-circle w-10 h-10 bg-brand-light text-brand">
                    <span class="material-symbols-outlined icon-md text-accent-orange">bolt</span>
                </span>
            </div>
            <div class="font-heading font-bold text-3xl text-text-primary tabular-nums">{{ $stats['sedang_progres'] }}</div>
            <div class="text-sm text-text-secondary mt-1">Sedang Progres</div>
        </a>
        <a href="{{ route('dashboard.dosen.mahasiswa-list', ['status' => 'tamat']) }}" class="p-5 rounded-card bg-bg-surface border border-border hover:border-sand/30 transition-colors">
            <div class="flex items-center justify-between mb-3">
                <span class="icon-circle w-10 h-10 bg-sand/15 text-sand">
                    <span class="material-symbols-outlined icon-md text-accent-purple">school</span>
                </span>
            </div>
            <div class="font-heading font-bold text-3xl text-text-primary tabular-nums">{{ $stats['tamat'] }}</div>
            <div class="text-sm text-text-secondary mt-1">Tamat</div>
        </a>
        <a href="{{ route('dashboard.dosen.sidang-list') }}" class="p-5 rounded-card bg-bg-surface border border-border hover:border-brand/30 transition-colors">
            <div class="flex items-center justify-between mb-3">
                <span class="icon-circle w-10 h-10 bg-brand-light text-brand">
                    <span class="material-symbols-outlined icon-md text-accent-purple">verified</span>
                </span>
            </div>
            <div class="font-heading font-bold text-3xl text-text-primary tabular-nums">{{ $stats['diuji'] }}</div>
            <div class="text-sm text-text-secondary mt-1">Diuji</div>
        </a>
        <a href="{{ route('quick-review.index') }}" class="p-5 rounded-card bg-bg-surface border border-border hover:border-status-danger/30 transition-colors">
            <div class="flex items-center justify-between mb-3">
                <span class="icon-circle w-10 h-10 bg-status-danger/15 text-status-danger">
                    <span class="material-symbols-outlined icon-md text-accent-blue">fact_check</span>
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
                                    <a href="{{ route('logbook.show', $entry) }}" class="px-3 py-1.5 rounded-xl bg-brand text-[#0b1420] text-xs font-medium hover:opacity-90">Review</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ===== Agenda Terdekat ===== --}}
    @if ($agendaTerdekat->isNotEmpty())
        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-heading font-semibold text-text-primary">Agenda Terdekat</h2>
                <span class="text-xs text-text-secondary">Jadwal seminar/sidang yang akan datang</span>
            </div>
            <div class="space-y-2">
                @foreach ($agendaTerdekat as $agenda)
                    <a href="{{ route('seminar-submission.show', $agenda) }}" class="flex items-center gap-3 p-3 rounded-xl bg-bg-panel border border-border hover:border-brand/30 transition-colors">
                        <span class="icon-circle w-10 h-10 bg-brand-light text-brand">
                            <span class="material-symbols-outlined icon-md text-status-info">event</span>
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-text-primary">{{ $agenda->mahasiswaTa->mahasiswa?->name }} — {{ $agenda->jenisLabel() }}</p>
                            <p class="text-xs text-text-secondary">{{ $agenda->tanggal->format('d M Y') }} · {{ $agenda->waktu?->format('H:i') }}{{ $agenda->lokasi ? ' · ' . $agenda->lokasi : '' }}</p>
                        </div>
                        <span class="text-brand text-xs font-medium">Lihat →</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ===== Submission Bahan Seminar/Sidang ===== --}}
    @if ($submissions->isNotEmpty())
        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-heading font-semibold text-text-primary">Bahan Seminar/Sidang ({{ $submissions->count() }})</h2>
                <span class="text-xs text-text-secondary">Pengiriman bahan dari mahasiswa</span>
            </div>
            <div class="space-y-2">
                @foreach ($submissions->take(10) as $submission)
                    <a href="{{ route('seminar-submission.show', $submission) }}" class="flex items-center gap-3 p-3 rounded-xl bg-bg-panel border border-border hover:border-brand/30 transition-colors">
                        <span class="icon-circle w-10 h-10 {{ $submission->status === \App\Models\SeminarSubmission::STATUS_SUBMITTED ? 'bg-status-success/15 text-status-success' : 'bg-bg-hover text-text-secondary' }}">
                            <span class="material-symbols-outlined icon-md text-accent-orange">upload_file</span>
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-text-primary">{{ $submission->mahasiswaTa->mahasiswa?->name }} — {{ $submission->jenisLabel() }}</p>
                            <p class="text-xs text-text-secondary">{{ $submission->statusLabel() }} · {{ $submission->tanggal->format('d M Y') }}</p>
                        </div>
                        <span class="text-brand text-xs font-medium">Lihat →</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

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
                                <td class="py-2.5 pr-4"><a href="{{ route($ta->isKp() ? 'mahasiswa-kp.show' : 'mahasiswa-ta.show', $ta) }}" class="hover:text-brand">{{ $ta->mahasiswa?->name }}</a> <span class="text-text-secondary text-xs">({{ $ta->mahasiswa?->nim }})</span></td>
                                <td class="py-2.5 pr-4 hidden md:table-cell">{{ $ta->isKp() ? ($ta->tempat_kp ?: '—') : $ta->judul_ta }}</td>
                                <td class="py-2.5 pr-4 text-xs hidden lg:table-cell">{{ $ta->pembimbing1?->name }}{{ $ta->pembimbing2 ? ' & ' . $ta->pembimbing2->name : '' }}</td>
                                <td class="py-2.5">
                                    <form method="POST" action="{{ route($ta->isKp() ? 'mahasiswa-kp.fase' : 'mahasiswa-ta.fase', $ta) }}" class="flex flex-col sm:flex-row sm:items-center gap-1.5" onsubmit="return confirm('Ubah fase {{ $ta->jenisLabel() }} ini? Pastikan sudah benar.')">
                                        @csrf
                                        @php $faseLabels = app(\App\Services\ProgramNamingService::class)->faseLabels($ta); @endphp
                                        <select name="fase" class="w-full sm:w-auto rounded-lg border border-border bg-bg-surface px-2 py-1.5 text-xs">
                                            @foreach ($faseLabels as $key => $label)
                                                <option value="{{ $key }}" @selected($ta->fase === $key)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <button class="w-full sm:w-auto px-3 py-1.5 rounded-lg bg-brand text-[#0b1420] text-xs font-medium hover:opacity-90">Update</button>
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
            filters.forEach(function (b) { b.classList.remove('bg-brand', 'text-[#0b1420]'); b.classList.add('bg-bg-panel', 'text-text-secondary'); });
            btn.classList.remove('bg-bg-panel', 'text-text-secondary');
            btn.classList.add('bg-brand', 'text-[#0b1420]');
            rows.forEach(function (r) { r.style.display = (f === 'all' || r.dataset.health === f) ? '' : 'none'; });
        });
    });
</script>
@endsection