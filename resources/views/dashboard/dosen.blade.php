@extends('layouts.app')

@section('title', 'Dashboard Dosen')

@section('content')
@php
    $studentList = route('dashboard.dosen.mahasiswa-list');
    $reviewList = route('materials-review.index');
    $health = [
        ['key' => 'green', 'label' => 'Sehat', 'badge' => 'badge-success', 'bar' => 'bg-status-success', 'text' => 'text-status-success'],
        ['key' => 'yellow', 'label' => 'Perhatian', 'badge' => 'badge-pending', 'bar' => 'bg-status-pending', 'text' => 'text-status-pending'],
        ['key' => 'red', 'label' => 'Kritis', 'badge' => 'badge-danger', 'bar' => 'bg-status-danger', 'text' => 'text-status-danger'],
    ];
    $healthTotal = array_sum($healthCount);
@endphp
<div class="mx-auto max-w-[1600px] space-y-4 lg:space-y-5">
    <x-page-header title="Dashboard Dosen" :description="'Selamat datang, '.auth()->user()->name.'. Berikut ringkasan aktivitas bimbingan dan tugas Anda.'">
        <x-slot:actions>
            <a href="{{ route('approval.index') }}" class="btn-primary inline-flex flex-1 items-center justify-center gap-2 px-4 py-2 text-sm font-semibold sm:flex-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
                <span class="material-symbols-outlined icon-sm" aria-hidden="true">person_add</span> Tambah Mahasiswa
            </a>
            <a href="{{ route('dosen-sidang.index') }}" class="btn-ghost inline-flex flex-1 items-center justify-center gap-2 px-4 py-2 text-sm font-medium sm:flex-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
                <span class="material-symbols-outlined icon-sm" aria-hidden="true">verified</span> Catat Sidang
            </a>
        </x-slot:actions>
    </x-page-header>

    @if ($pendingMaterialsCount > 0 || $pendingRegistrations > 0)
        <div role="status" class="flex flex-wrap items-center gap-2 rounded-control border border-status-pending/40 bg-status-pending/10 px-4 py-2.5 text-sm">
            <span class="material-symbols-outlined icon-md text-status-pending" aria-hidden="true">priority_high</span>
            <p class="min-w-0 flex-1 text-text-primary">
                @if ($pendingMaterialsCount > 0)
                    Ada <strong>{{ $pendingMaterialsCount }} bahan menunggu review</strong> (logbook, revisi, atau seminar).
                @endif
                @if ($pendingRegistrations > 0)
                    <strong>{{ $pendingRegistrations }} permintaan persetujuan mahasiswa</strong> menunggu keputusan Anda.
                @endif
            </p>
            @if ($pendingMaterialsCount > 0)
                <a href="{{ $reviewList }}" class="font-semibold text-brand hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Lihat Antrean →</a>
            @endif
            @if ($pendingRegistrations > 0)
                <a href="{{ route('approval.index') }}" class="font-semibold text-brand hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Persetujuan →</a>
            @endif
        </div>
    @endif

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Ringkasan statistik dosen">
        @foreach ([
            ['label' => 'Mahasiswa Aktif', 'value' => $stats['sedang_progres'], 'icon' => 'group', 'href' => route('dashboard.dosen.mahasiswa-list', ['status' => 'aktif']), 'context' => 'Bimbingan dan pengujian aktif'],
            ['label' => 'Menunggu Review', 'value' => $pendingMaterialsCount, 'icon' => 'rate_review', 'href' => $reviewList, 'context' => 'Bahan belum ditinjau'],
            ['label' => 'Perlu Perhatian', 'value' => $needsAttention, 'icon' => 'monitor_heart', 'href' => $studentList, 'context' => 'Status perhatian atau kritis'],
            ['label' => 'Persetujuan', 'value' => $pendingRegistrations, 'icon' => 'how_to_reg', 'href' => route('approval.index'), 'context' => 'Permintaan mahasiswa'],
        ] as $stat)
            <a href="{{ $stat['href'] }}" class="card flex min-w-0 items-center gap-3 p-3.5 transition-colors hover:border-brand/50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand sm:p-4">
                <span class="icon-chip h-10 w-10"><span class="material-symbols-outlined icon-md" aria-hidden="true">{{ $stat['icon'] }}</span></span>
                <span class="min-w-0">
                    <span class="block font-heading text-2xl font-bold tabular-nums text-text-primary">{{ $stat['value'] }}</span>
                    <span class="block text-xs font-semibold text-text-primary sm:text-sm">{{ $stat['label'] }}</span>
                    <span class="hidden text-xs text-text-secondary sm:block">{{ $stat['context'] }}</span>
                </span>
            </a>
        @endforeach
    </div>

    <div class="grid min-w-0 gap-4 xl:grid-cols-[minmax(0,1.65fr)_minmax(0,1fr)]">
        <section class="card min-w-0 p-4 sm:p-5" aria-labelledby="review-heading">
            <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h2 id="review-heading" class="font-heading font-semibold text-text-primary">Antrean Review</h2>
                    <p class="text-xs text-text-secondary">Logbook dan revisi terbaru yang perlu ditinjau.</p>
                </div>
                <a href="{{ $reviewList }}" class="text-sm font-semibold text-brand hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Lihat Semua ({{ $pendingMaterialsCount }}) →</a>
            </div>
            @if ($queue->isEmpty())
                <p class="rounded-control bg-bg-panel p-4 text-sm text-text-secondary">Tidak ada logbook atau revisi yang menunggu review. @if ($pendingMaterialsCount > 0)Periksa bahan seminar di antrean review.@endif</p>
            @else
                <div class="divide-y divide-border rounded-control border border-border">
                    @foreach ($queue as $entry)
                        <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-2 px-3 py-2.5 sm:flex-nowrap">
                            <span class="avatar h-9 w-9 text-xs" aria-hidden="true">{{ $entry->mahasiswaTa?->mahasiswa?->initials() ?: 'M' }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-text-primary">{{ $entry->mahasiswaTa?->mahasiswa?->name ?? 'Mahasiswa' }}</p>
                                <p class="truncate text-xs text-text-secondary">{{ $entry->mahasiswaTa?->mahasiswa?->nim }}@if ($entry->topik) · {{ $entry->topik }}@endif</p>
                            </div>
                            <span class="badge {{ $entry->jenis === \App\Models\LogbookEntry::JENIS_REVISI ? 'badge-pending' : 'badge-info' }}">{{ $entry->jenis === \App\Models\LogbookEntry::JENIS_REVISI ? 'Revisi' : 'Logbook' }}</span>
                            <span class="w-24 text-right text-xs text-text-secondary sm:w-28">{{ $entry->submitted_at?->diffForHumans() ?? 'Waktu tidak tersedia' }}</span>
                            <a href="{{ route('logbook.show', $entry) }}" aria-label="Review {{ $entry->jenis }} {{ $entry->mahasiswaTa?->mahasiswa?->name ?? 'mahasiswa' }}" class="btn-primary inline-flex items-center justify-center px-3 py-1.5 text-xs font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">Review</a>
                        </div>
                    @endforeach
                </div>
            @endif
            @if ($pendingMaterialsCount > $queueCount)
                <p class="mt-2 text-xs text-text-secondary">Bahan seminar/sidang belum dibaca juga tersedia di <a href="{{ $reviewList }}" class="font-semibold text-brand hover:underline">antrean review bahan</a>.</p>
            @endif
        </section>

        <div class="grid min-w-0 content-start gap-4">
            <section class="card min-w-0 p-4 sm:p-5" aria-labelledby="guidance-heading">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 id="guidance-heading" class="font-heading font-semibold text-text-primary">Ringkasan Bimbingan</h2>
                    <a href="{{ $studentList }}" class="text-xs font-semibold text-brand hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Lihat Detail →</a>
                </div>
                @if ($healthTotal === 0)
                    <p class="text-sm text-text-secondary">Belum ada mahasiswa untuk dipantau.</p>
                @else
                    <div class="grid grid-cols-3 gap-2">
                        @foreach ($health as $item)
                            <div class="min-w-0 rounded-control bg-bg-panel px-2 py-3 text-center sm:px-3">
                                <span class="block text-xl font-bold tabular-nums text-text-primary">{{ $healthCount[$item['key']] }}</span>
                                <span class="block text-xs font-semibold {{ $item['text'] }}">{{ $item['label'] }}</span>
                                <span class="block text-xs text-text-secondary">{{ round($healthCount[$item['key']] / $healthTotal * 100) }}%</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="card min-w-0 p-4 sm:p-5" aria-labelledby="agenda-heading">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 id="agenda-heading" class="font-heading font-semibold text-text-primary">Agenda Terdekat</h2>
                    <a href="{{ route('dosen.seminar-jadwal') }}" class="text-xs font-semibold text-brand hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Lihat Semua Agenda →</a>
                </div>
                @forelse ($agendaTerdekat as $agenda)
                    <a href="{{ route('seminar-submission.show', $agenda) }}" class="flex min-w-0 items-center gap-3 border-t border-border py-2 first:border-0 first:pt-0 hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">
                        <span class="flex h-11 w-11 shrink-0 flex-col items-center justify-center rounded-control bg-brand/10 text-brand"><span class="text-base font-bold leading-5">{{ $agenda->tanggal->format('d') }}</span><span class="text-[10px]">{{ $agenda->tanggal->format('M') }}</span></span>
                        <span class="min-w-0 flex-1"><span class="block truncate text-xs font-semibold text-text-primary">{{ $agenda->jenisLabel() }}</span><span class="block truncate text-xs text-text-secondary">{{ $agenda->mahasiswaTa?->mahasiswa?->name ?? 'Mahasiswa' }}</span><span class="block truncate text-xs text-text-secondary">{{ $agenda->waktu?->format('H:i') ?? 'Waktu belum ditentukan' }}@if ($agenda->lokasi) · {{ $agenda->lokasi }}@endif</span></span>
                        <span class="material-symbols-outlined icon-sm text-brand" aria-hidden="true">chevron_right</span>
                    </a>
                @empty
                    <p class="text-sm text-text-secondary">Tidak ada agenda seminar atau sidang dalam waktu dekat.</p>
                @endforelse
            </section>
        </div>
    </div>

    <section class="card min-w-0 p-4 sm:p-5" aria-labelledby="priority-heading">
        <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
            <div><h2 id="priority-heading" class="font-heading font-semibold text-text-primary">Mahasiswa Prioritas</h2><p class="text-xs text-text-secondary">Status kritis dan perhatian ditampilkan lebih dulu.</p></div>
            <a href="{{ $studentList }}" class="text-sm font-semibold text-brand hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Lihat Semua Mahasiswa →</a>
        </div>
        @if ($priorityStudents->isEmpty())
                <p class="text-sm text-text-secondary">Tidak ada mahasiswa yang memerlukan perhatian khusus.</p>
        @else
            <div class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
                @foreach ($priorityStudents as $row)
                    @php
                        $ta = $row['ta'];
                        $status = collect($health)->firstWhere('key', $row['regularity']);
                    @endphp
                    <a href="{{ route($ta->isKp() ? 'mahasiswa-kp.show' : 'mahasiswa-ta.show', $ta) }}" class="min-w-0 rounded-control border border-border bg-bg-panel p-3 transition-colors hover:border-brand/50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand" aria-label="Detail {{ $ta->mahasiswa?->name ?? 'mahasiswa' }}, {{ $status['label'] ?? 'Status tidak tersedia' }}">
                        <div class="flex min-w-0 items-start gap-2.5">
                            <span class="avatar h-9 w-9 text-xs" aria-hidden="true">{{ $ta->mahasiswa?->initials() ?: 'M' }}</span>
                            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-text-primary">{{ $ta->mahasiswa?->name ?? 'Mahasiswa' }}</span><span class="block truncate font-mono text-xs text-text-secondary">{{ $ta->mahasiswa?->nim }}</span></span>
                            <span class="badge {{ $status['badge'] ?? 'badge-neutral' }}">{{ $status['label'] ?? '—' }}</span>
                        </div>
                        <p class="mt-2 truncate text-xs text-text-secondary">{{ $ta->jenisLabel() }} · Fase: <span class="text-text-primary">{{ $ta->faseLabel() }}</span></p>
                        <p class="mt-1 truncate text-xs text-text-secondary" title="{{ $row['tooltip'] }}">{{ $row['tooltip'] }}@if ($row['menunggu']) · {{ $row['menunggu'] }} menunggu @endif</p>
                        <div class="mt-2 flex items-center gap-2"><div class="h-1.5 min-w-0 flex-1 overflow-hidden rounded-full bg-bg-hover" role="progressbar" aria-label="Progres sesi bimbingan {{ $ta->mahasiswa?->name ?? 'mahasiswa' }}" aria-valuemin="0" aria-valuemax="{{ max(1, $row['target']) }}" aria-valuenow="{{ min(max(0, $row['approved']), max(1, $row['target'])) }}" aria-valuetext="{{ $row['approved'] }} dari {{ $row['target'] }} sesi disetujui"><div class="h-full rounded-full bg-brand" style="width: {{ min(100, max(0, $row['percent'])) }}%"></div></div><span class="text-xs tabular-nums text-text-secondary">{{ $row['approved'] }}/{{ $row['target'] }}</span><span class="material-symbols-outlined icon-sm text-brand" aria-hidden="true">chevron_right</span></div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    <div class="grid min-w-0 gap-4 lg:grid-cols-2">
        <section class="card min-w-0 p-4 sm:p-5" aria-labelledby="health-heading">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2"><h2 id="health-heading" class="font-heading font-semibold text-text-primary">Health Bimbingan</h2><a href="{{ $studentList }}" class="text-xs font-semibold text-brand hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Lihat Monitoring Mahasiswa →</a></div>
            @if ($healthTotal === 0)
                <p class="text-sm text-text-secondary">Belum ada mahasiswa untuk dipantau.</p>
            @else
                <div class="space-y-2.5">
                    @foreach ($health as $item)
                        @php $percentage = round($healthCount[$item['key']] / $healthTotal * 100); @endphp
                        <div class="flex items-center gap-2 text-xs"><span class="w-24 shrink-0 {{ $item['text'] }}">{{ $item['label'] }}</span><div class="h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-bg-hover" role="img" aria-label="{{ $item['label'] }}: {{ $healthCount[$item['key']] }} dari {{ $healthTotal }} mahasiswa, {{ $percentage }} persen"><div class="h-full rounded-full {{ $item['bar'] }}" style="width: {{ $percentage }}%"></div></div><span class="w-16 text-right tabular-nums text-text-secondary">{{ $healthCount[$item['key']] }} ({{ $percentage }}%)</span></div>
                    @endforeach
                </div>
            @endif
        </section>
        <section class="card min-w-0 p-4 sm:p-5" aria-labelledby="phase-heading">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2"><h2 id="phase-heading" class="font-heading font-semibold text-text-primary">Distribusi Fase</h2><a href="{{ route('dosen.mahasiswa-saya') }}" class="text-xs font-semibold text-brand hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Kelola Fase Mahasiswa →</a></div>
            @if ($phaseDistribution->isEmpty())
                <p class="text-sm text-text-secondary">Belum ada fase mahasiswa untuk diringkas.</p>
            @else
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach ($phaseDistribution as $phase)
                        <div class="min-w-0 rounded-control border border-border bg-bg-panel px-3 py-2"><span class="block truncate text-xs text-text-secondary" title="{{ $phase['program'] }} · {{ $phase['label'] }}">{{ $phase['program'] }} · {{ $phase['label'] }}</span><span class="block text-lg font-bold tabular-nums text-text-primary">{{ $phase['count'] }}</span></div>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-text-secondary">Pilih mahasiswa dari daftar untuk memperbarui fasenya.</p>
            @endif
        </section>
    </div>
</div>
@endsection