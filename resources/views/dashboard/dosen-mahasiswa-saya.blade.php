@extends('layouts.app')

@section('title', 'Mahasiswa Saya')

@section('content')
@php
    $badgeMap = [
        \App\Models\MahasiswaTa::STATUS_AKTIF => 'badge-info',
        \App\Models\MahasiswaTa::STATUS_TAMAT => 'badge-success',
        \App\Models\MahasiswaTa::STATUS_NONAKTIF => 'badge-neutral',
    ];
    $detailRoute = fn ($ta) => $ta->isKp() ? 'mahasiswa-kp.show' : 'mahasiswa-ta.show';
@endphp
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading font-bold text-2xl text-text-primary">Mahasiswa Saya</h1>
            <p class="text-sm text-text-secondary mt-0.5">Mahasiswa yang Anda bimbing atau uji (TA/KP)</p>
        </div>
        <a href="{{ route('dashboard') }}" class="px-4 py-2 rounded-control bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">← Dashboard</a>
    </div>

    {{-- ===== Dibimbing ===== --}}
    <div class="card p-6">
        <h2 class="font-heading font-semibold text-text-primary mb-4 flex items-center gap-2">
            <span class="icon-circle w-8 h-8 bg-brand-light text-brand"><span class="material-symbols-outlined icon-sm">school</span></span>
            Dibimbing ({{ $dibimbing->count() }})
        </h2>
        @if ($dibimbing->isEmpty())
            <p class="text-sm text-text-secondary">Belum ada mahasiswa yang Anda bimbing.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border">
                            <th class="py-2 pr-4 font-medium">Mahasiswa</th>
                            <th class="py-2 pr-4 font-medium">Jenis</th>
                            <th class="py-2 pr-4 font-medium">Status</th>
                            <th class="py-2 pr-4 font-medium">Fase</th>
                            <th class="py-2 pr-4 font-medium">Peran</th>
                            <th class="py-2 font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dibimbing as $ta)
                            @php $mhs = $ta->mahasiswa; @endphp
                            <tr class="border-b border-border last:border-0 hover:bg-bg-hover cursor-pointer" onclick="window.location='{{ route($detailRoute($ta), $ta) }}'">
                                <td class="py-2.5 pr-4">
                                    <div class="flex items-center gap-2.5">
                                        <span class="avatar w-8 h-8 text-xs shrink-0">
                                            @if ($mhs && $mhs->photoUrl())
                                                <img src="{{ $mhs->photoUrl() }}" class="h-full w-full rounded-full object-cover" alt="">
                                            @else
                                                {{ $mhs?->initials() }}
                                            @endif
                                        </span>
                                        <div class="min-w-0">
                                            <p class="font-medium text-text-primary truncate">{{ $mhs?->name ?? '—' }}</p>
                                            @if ($mhs)
                                                <p class="text-xs text-text-secondary font-mono">{{ $mhs->identifier }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="py-2.5 pr-4">
                                    <span class="badge {{ $ta->isKp() ? 'badge-neutral' : 'badge-info' }}">{{ $ta->jenisLabel() }}</span>
                                </td>
                                <td class="py-2.5 pr-4">
                                    <span class="badge {{ $badgeMap[$ta->status_ta] ?? 'badge-neutral' }}">{{ ucfirst($ta->status_ta) }}</span>
                                </td>
                                <td class="py-2.5 pr-4 text-text-secondary">{{ $ta->faseLabel() }}</td>
                                <td class="py-2.5 pr-4">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($ta->my_roles as $role)
                                            <span class="badge {{ str_starts_with($role, 'Pembimbing') ? 'badge-info' : 'badge-neutral' }}">{{ $role }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="py-2.5">
                                    <a href="{{ route($detailRoute($ta), $ta) }}" class="text-brand hover:underline inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined icon-sm text-status-info">open_in_new</span> Profil
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
{{-- ===== Diuji ===== --}}
    <div class="card p-6">
        <h2 class="font-heading font-semibold text-text-primary mb-4 flex items-center gap-2">
            <span class="icon-circle w-8 h-8 bg-brand-light text-brand"><span class="material-symbols-outlined icon-sm">verified</span></span>
            Diuji ({{ $diuji->count() }})
        </h2>
        @if ($diuji->isEmpty())
            <p class="text-sm text-text-secondary">Belum ada mahasiswa yang Anda uji.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border">
                            <th class="py-2 pr-4 font-medium">Mahasiswa</th>
                            <th class="py-2 pr-4 font-medium">Jenis</th>
                            <th class="py-2 pr-4 font-medium">Status</th>
                            <th class="py-2 pr-4 font-medium">Fase</th>
                            <th class="py-2 pr-4 font-medium">Peran</th>
                            <th class="py-2 font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($diuji as $ta)
                            @php $mhs = $ta->mahasiswa; @endphp
                            <tr class="border-b border-border last:border-0 hover:bg-bg-hover cursor-pointer" onclick="window.location='{{ route($detailRoute($ta), $ta) }}'">
                                <td class="py-2.5 pr-4">
                                    <div class="flex items-center gap-2.5">
                                        <span class="avatar w-8 h-8 text-xs shrink-0">
                                            @if ($mhs && $mhs->photoUrl())
                                                <img src="{{ $mhs->photoUrl() }}" class="h-full w-full rounded-full object-cover" alt="">
                                            @else
                                                {{ $mhs?->initials() }}
                                            @endif
                                        </span>
                                        <div class="min-w-0">
                                            <p class="font-medium text-text-primary truncate">{{ $mhs?->name ?? '—' }}</p>
                                            @if ($mhs)
                                                <p class="text-xs text-text-secondary font-mono">{{ $mhs->identifier }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="py-2.5 pr-4">
                                    <span class="badge {{ $ta->isKp() ? 'badge-neutral' : 'badge-info' }}">{{ $ta->jenisLabel() }}</span>
                                </td>
                                <td class="py-2.5 pr-4">
                                    <span class="badge {{ $badgeMap[$ta->status_ta] ?? 'badge-neutral' }}">{{ ucfirst($ta->status_ta) }}</span>
                                </td>
                                <td class="py-2.5 pr-4 text-text-secondary">{{ $ta->faseLabel() }}</td>
                                <td class="py-2.5 pr-4">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($ta->my_roles as $role)
                                            <span class="badge {{ str_starts_with($role, 'Pembimbing') ? 'badge-info' : 'badge-neutral' }}">{{ $role }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="py-2.5">
                                    <a href="{{ route($detailRoute($ta), $ta) }}" class="text-brand hover:underline inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined icon-sm text-status-info">open_in_new</span> Profil
                                    </a>
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
