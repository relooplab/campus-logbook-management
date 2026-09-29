@php
    $badgeMap = [
        \App\Models\MahasiswaTa::STATUS_AKTIF => 'badge-info',
        \App\Models\MahasiswaTa::STATUS_TAMAT => 'badge-success',
        \App\Models\MahasiswaTa::STATUS_NONAKTIF => 'badge-neutral',
    ];
    $naming = app(\App\Services\ProgramNamingService::class);
@endphp
<div class="hidden lg:block">
    <table class="w-full table-fixed text-left text-sm">
        <thead class="bg-bg-panel text-xs text-text-secondary"><tr><th scope="col" class="w-[26%] px-2 py-2 font-medium xl:w-[21%]">Mahasiswa</th><th scope="col" class="w-[9%] px-2 py-2 font-medium xl:w-[8%]">Program</th><th scope="col" class="w-[23%] px-2 py-2 font-medium xl:w-[21%]">Fase</th><th scope="col" class="w-[17%] px-2 py-2 font-medium xl:w-[16%]">Peran</th><th scope="col" class="w-[11%] px-2 py-2 font-medium xl:w-[10%]">Status</th><th scope="col" class="hidden w-[15%] px-2 py-2 font-medium xl:table-cell">Terakhir Aktif</th><th scope="col" class="w-[14%] px-2 py-2 font-medium xl:w-[9%]">Aksi</th></tr></thead>
        <tbody class="divide-y divide-border">@foreach ($rows as $ta)
            @php $student = $ta->mahasiswa; $detail = route($ta->isKp() ? 'mahasiswa-kp.show' : 'mahasiswa-ta.show', $ta); @endphp
            <tr class="hover:bg-bg-hover">
                <td class="px-2 py-2"><div class="flex min-w-0 items-center gap-2"><span class="avatar h-8 w-8 shrink-0 text-xs">@if ($student?->photoUrl())<img src="{{ $student->photoUrl() }}" alt="" class="h-full w-full rounded-full object-cover">@else{{ $student?->initials() }}@endif</span><div class="min-w-0"><div class="truncate font-semibold text-text-primary" title="{{ $student?->name }}">{{ $student?->name ?? '—' }}</div><div class="truncate font-mono text-xs text-text-secondary">{{ $student?->nim }}</div></div></div></td>
                <td class="px-2 py-2"><span class="badge {{ $ta->isKp() ? 'badge-neutral' : 'badge-info' }}">{{ $ta->jenisLabel() }}</span></td>
                <td class="px-2 py-2">@include('dashboard.partials.dosen-phase-control', ['controlId' => 'desktop'])</td>
                <td class="px-2 py-2"><div class="flex flex-wrap gap-1">@foreach ($ta->my_roles as $role)<span class="badge {{ str_starts_with($role, 'Pembimbing') ? 'badge-info' : 'badge-neutral' }}">{{ $role }}</span>@endforeach</div></td>
                <td class="px-2 py-2"><span class="badge {{ $badgeMap[$ta->status_ta] ?? 'badge-neutral' }}">{{ ucfirst($ta->status_ta) }}</span></td>
                <td class="hidden px-2 py-2 text-xs text-text-secondary xl:table-cell">{{ $student?->last_active_at?->format('d M Y H:i') ?? '—' }}</td>
                <td class="px-2 py-2"><a href="{{ $detail }}" aria-label="Lihat profil {{ $student?->name ?? 'mahasiswa' }} ({{ $ta->jenisLabel() }})" class="inline-flex rounded-control px-2 py-1 text-brand hover:bg-brand-light focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Lihat <span class="material-symbols-outlined icon-sm" aria-hidden="true">arrow_forward</span></a></td>
            </tr>
        @endforeach</tbody>
    </table>
</div>
<div class="space-y-2 lg:hidden">@foreach ($rows as $ta)
    @php $student = $ta->mahasiswa; @endphp
    <article class="rounded-control border border-border bg-bg-panel p-3">
        <div class="flex items-start justify-between gap-2"><div class="flex min-w-0 items-center gap-2"><span class="avatar h-9 w-9 shrink-0 text-xs">@if ($student?->photoUrl())<img src="{{ $student->photoUrl() }}" alt="" class="h-full w-full rounded-full object-cover">@else{{ $student?->initials() }}@endif</span><div class="min-w-0"><div class="break-words text-sm font-semibold text-text-primary">{{ $student?->name ?? '—' }}</div><div class="font-mono text-xs text-text-secondary">{{ $student?->nim }}</div></div></div><span class="badge shrink-0 {{ $badgeMap[$ta->status_ta] ?? 'badge-neutral' }}">{{ ucfirst($ta->status_ta) }}</span></div>
        <div class="mt-3 flex flex-wrap items-center gap-1.5"><span class="badge {{ $ta->isKp() ? 'badge-neutral' : 'badge-info' }}">{{ $ta->jenisLabel() }}</span>@foreach ($ta->my_roles as $role)<span class="badge {{ str_starts_with($role, 'Pembimbing') ? 'badge-info' : 'badge-neutral' }}">{{ $role }}</span>@endforeach</div>
        <div class="mt-3 text-sm text-text-secondary">Fase: <span class="text-text-primary">{{ $ta->faseLabel() }}</span></div>
        <div class="mt-2">@include('dashboard.partials.dosen-phase-control', ['controlId' => 'mobile'])</div>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-text-secondary"><span>Terakhir aktif: {{ $student?->last_active_at?->format('d M Y H:i') ?? '—' }}</span><a href="{{ route($ta->isKp() ? 'mahasiswa-kp.show' : 'mahasiswa-ta.show', $ta) }}" class="font-semibold text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Lihat Profil →</a></div>
    </article>
@endforeach</div>