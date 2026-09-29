@extends('layouts.app')

@section('title', 'Mahasiswa Saya')

@section('content')
@php
    $url = fn (array $changes) => route('dosen.mahasiswa-saya', array_merge(request()->except('page'), $changes));
    $groups = collect($phaseOptions)->filter(fn ($label, $key) => ($distribution[$key]->total ?? 0) > 0);
@endphp
<div class="min-w-0 space-y-4">
    <header class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading text-2xl font-bold text-text-primary">Mahasiswa Saya</h1>
            <p class="mt-0.5 text-sm text-text-secondary">Kelola mahasiswa yang Anda bimbing atau uji pada TA/KP.</p>
        </div>
        <a href="{{ route('dashboard') }}" class="btn-ghost px-3 py-2 text-sm font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">← Dashboard</a>
    </header>

    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-5" aria-label="Ringkasan mahasiswa">
        @foreach (['total' => ['Total Mahasiswa', 'groups'], 'pembimbing' => ['Dibimbing', 'school'], 'penguji' => ['Diuji', 'how_to_reg'], 'aktif' => ['Aktif', 'check_circle'], 'nonaktif' => ['Nonaktif', 'pause_circle']] as $key => [$label, $icon])
            <div class="card flex items-center gap-3 px-3 py-3 sm:px-4">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-control bg-brand-light text-brand" aria-hidden="true"><span class="material-symbols-outlined icon-md">{{ $icon }}</span></span>
                <div class="min-w-0"><div class="text-xl font-bold tabular-nums leading-tight text-text-primary">{{ $metrics[$key] }}</div><div class="text-xs text-text-secondary">{{ $label }}</div></div>
            </div>
        @endforeach
    </div>

    <form method="GET" action="{{ route('dosen.mahasiswa-saya') }}" class="card grid gap-2 p-3 sm:grid-cols-2 lg:grid-cols-[minmax(190px,2fr)_repeat(4,minmax(105px,1fr))_auto] lg:items-end" aria-label="Cari dan filter mahasiswa">
        <input type="hidden" name="tab" value="{{ $filters['tab'] }}">
        <input type="hidden" name="view" value="{{ $filters['view'] }}">
        <label class="block min-w-0 text-xs font-medium text-text-secondary">Cari mahasiswa
            <input name="search" type="search" value="{{ $filters['search'] }}" placeholder="Cari nama mahasiswa atau NIM..." class="mt-1 w-full rounded-control border border-border bg-bg-surface px-3 py-2 text-sm text-text-primary placeholder:text-text-secondary focus:outline-none focus:ring-2 focus:ring-brand/40">
        </label>
        <label class="block min-w-0 text-xs font-medium text-text-secondary">Program
            <select name="program" class="mt-1 w-full rounded-control border border-border bg-bg-surface px-2 py-2 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-brand/40"><option value="">Semua Program</option>@foreach ($programs as $program)<option value="{{ $program }}" @selected(($filters['program'] ?? '') === $program)>{{ strtoupper($program) }}</option>@endforeach</select>
        </label>
        <label class="block min-w-0 text-xs font-medium text-text-secondary">Fase
            <select name="fase" class="mt-1 w-full rounded-control border border-border bg-bg-surface px-2 py-2 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-brand/40"><option value="">Semua Fase</option>@foreach ($phaseOptions as $key => $label)<option value="{{ $key }}" @selected(($filters['fase'] ?? '') === $key)>{{ $label }}</option>@endforeach</select>
        </label>
        <label class="block min-w-0 text-xs font-medium text-text-secondary">Status
            <select name="status" class="mt-1 w-full rounded-control border border-border bg-bg-surface px-2 py-2 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-brand/40"><option value="">Semua Status</option>@foreach ($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
        </label>
        <label class="block min-w-0 text-xs font-medium text-text-secondary">Peran
            <select name="peran" class="mt-1 w-full rounded-control border border-border bg-bg-surface px-2 py-2 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-brand/40"><option value="">Semua Peran</option><option value="pembimbing" @selected(($filters['peran'] ?? '') === 'pembimbing')>Pembimbing</option><option value="penguji" @selected(($filters['peran'] ?? '') === 'penguji')>Penguji</option></select>
        </label>
        <div class="flex items-center gap-2 sm:col-span-2 lg:col-span-1"><button class="btn-primary px-3 py-2 text-sm font-semibold">Terapkan</button><a href="{{ route('dosen.mahasiswa-saya') }}" class="btn-ghost px-3 py-2 text-sm">Reset</a></div>
    </form>

    <section class="card min-w-0 p-3 sm:p-4" aria-label="Daftar mahasiswa">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <nav class="flex flex-wrap gap-1" aria-label="Hubungan dengan mahasiswa">
                @foreach (['semua' => ['Semua', 'total'], 'pembimbing' => ['Dibimbing', 'pembimbing'], 'penguji' => ['Diuji', 'penguji']] as $key => [$label, $count])
                    <a href="{{ $url(['tab' => $key]) }}" @if ($filters['tab'] === $key) aria-current="page" @endif class="rounded-control border px-3 py-2 text-xs font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand {{ $filters['tab'] === $key ? 'border-brand bg-brand-light text-brand' : 'border-transparent bg-bg-panel text-text-secondary hover:bg-bg-hover' }}">{{ $label }} ({{ $metrics[$count] }})</a>
                @endforeach
            </nav>
            <div class="flex flex-wrap items-center gap-2 text-xs text-text-secondary"><span>Tampilan:</span>
                @foreach (['daftar' => 'Daftar', 'fase' => 'Kelompokkan per Fase'] as $key => $label)
                    <a href="{{ $url(['view' => $key]) }}" @if ($filters['view'] === $key) aria-current="page" @endif class="rounded-control border px-3 py-2 font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand {{ $filters['view'] === $key ? 'border-brand bg-brand-light text-brand' : 'border-border text-text-secondary hover:bg-bg-hover' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        @if ($filters['view'] === 'fase' && $groups->isNotEmpty())
            <div class="mb-3 rounded-control border border-border bg-bg-panel p-3">
                <h2 class="mb-2 text-sm font-semibold text-text-primary">Distribusi Mahasiswa per Fase</h2>
                <div class="flex flex-wrap gap-2">@foreach ($groups as $key => $label)
                    @if ($students->getCollection()->contains(fn ($ta) => $ta->jenis.':'.$ta->fase === $key))
                        <a href="#fase-{{ str_replace(':', '-', $key) }}" class="rounded-control border border-border bg-bg-surface px-2.5 py-1.5 text-xs text-text-primary hover:border-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">{{ $label }} <strong class="ml-1 tabular-nums">{{ $distribution[$key]->total }}</strong></a>
                    @else
                        <span class="rounded-control border border-border bg-bg-surface px-2.5 py-1.5 text-xs text-text-secondary" title="Tidak ada di halaman ini">{{ $label }} <strong class="ml-1 tabular-nums">{{ $distribution[$key]->total }}</strong></span>
                    @endif
                @endforeach</div>
            </div>
        @endif

        @if ($students->isEmpty())
            <div class="rounded-control bg-bg-panel px-4 py-12 text-center text-sm text-text-secondary">{{ $metrics['total'] === 0 ? 'Belum ada mahasiswa yang terkait dengan Anda.' : 'Tidak ada mahasiswa yang cocok dengan pencarian atau filter.' }}</div>
        @elseif ($filters['view'] === 'daftar')
            @include('dashboard.partials.dosen-student-rows', ['rows' => $students])
        @else
            @php
                $pageGroups = $students->getCollection()->groupBy(fn ($ta) => $ta->jenis.':'.$ta->fase);
                $orderedGroups = $groups->filter(fn ($label, $key) => $pageGroups->has($key));
            @endphp
            <div class="space-y-2">@foreach ($orderedGroups as $key => $label)
                <details id="fase-{{ str_replace(':', '-', $key) }}" class="group rounded-control border border-border bg-bg-surface" @if ($loop->first) open @endif>
                    <summary class="cursor-pointer list-none rounded-control px-3 py-3 text-sm font-semibold text-text-primary hover:bg-bg-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand [&::-webkit-details-marker]:hidden"><span class="material-symbols-outlined icon-sm mr-1 transition-transform group-open:rotate-90" aria-hidden="true">chevron_right</span>{{ $label }} <span class="ml-2 text-xs font-normal text-text-secondary">{{ $distribution[$key]->total }} mahasiswa · {{ $pageGroups[$key]->count() }} di halaman ini</span></summary>
                    <div class="px-2 pb-2 sm:px-3">@include('dashboard.partials.dosen-student-rows', ['rows' => $pageGroups[$key]])</div>
                </details>
            @endforeach</div>
        @endif

        @if ($students->total())
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-3 text-xs text-text-secondary"><span>Menampilkan {{ $students->firstItem() }}–{{ $students->lastItem() }} dari {{ $students->total() }} program mahasiswa</span>{{ $students->links() }}</div>
        @endif
    </section>
</div>
@endsection