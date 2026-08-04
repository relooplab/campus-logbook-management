@extends('layouts.app')

@section('title', 'Logbook')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading font-bold text-2xl text-text-primary">Logbook Bimbingan</h1>
            <p class="text-sm text-text-secondary mt-0.5">Daftar entri logbook & revisi</p>
        </div>
        @auth
            @if (auth()->user()->isMahasiswa())
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('logbook.create') }}" class="px-4 py-2 rounded-xl bg-brand text-white text-sm font-medium hover:opacity-90 inline-flex items-center gap-1.5">
                        <span class="material-symbols-outlined icon-sm">add</span> + Logbook
                    </a>
                    <a href="{{ route('logbook.create-revisi') }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">+ Entri Revisi</a>
                </div>
            @endif
        @endauth
    </div>

    {{-- Filter kombinasi --}}
    <form method="GET" action="{{ route('logbook.index') }}" class="card p-4 flex flex-wrap gap-3 items-end">
        <div class="w-full sm:w-auto">
            <label class="block text-xs text-text-secondary mb-1">Status</label>
            <select name="status" class="w-full sm:w-auto rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                <option value="">Semua</option>
                @foreach (['draft' => 'Draf', 'submitted' => 'Dikirim', 'approved' => 'Disetujui', 'revisi' => 'Revisi'] as $v => $l)
                    <option value="{{ $v }}" @selected(($filters['status'] ?? '') === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div class="w-full sm:w-auto">
            <label class="block text-xs text-text-secondary mb-1">Jenis</label>
            <select name="jenis" class="w-full sm:w-auto rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                <option value="">Semua</option>
                <option value="logbook" @selected(($filters['jenis'] ?? '') === 'logbook')>Logbook</option>
                <option value="revisi" @selected(($filters['jenis'] ?? '') === 'revisi')>Revisi</option>
            </select>
        </div>
        <div class="w-full sm:w-auto">
            <label class="block text-xs text-text-secondary mb-1">Dari tanggal</label>
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                class="w-full sm:w-auto rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
        </div>
        <div class="w-full sm:w-auto">
            <label class="block text-xs text-text-secondary mb-1">Sampai tanggal</label>
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                class="w-full sm:w-auto rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
        </div>
        <div class="w-full sm:w-auto">
            <label class="block text-xs text-text-secondary mb-1">Kata kunci</label>
            <input type="text" name="keyword" value="{{ $filters['keyword'] ?? '' }}" placeholder="Topik / nama / isi"
                class="w-full sm:w-auto rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
        </div>
        <div class="flex gap-2 w-full sm:w-auto">
            <button type="submit" class="flex-1 sm:flex-none px-4 py-2 rounded-xl bg-brand text-white text-sm font-medium hover:opacity-90">Cari</button>
            <a href="{{ route('logbook.index') }}" class="flex-1 sm:flex-none px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border text-center">Reset</a>
        </div>
    </form>

    @if ($entries->isEmpty())
        <div class="px-4 py-10 rounded-xl bg-bg-panel border border-border text-center text-text-secondary">
            <span class="material-symbols-outlined icon-lg mb-2 text-text-secondary/50">inbox</span>
            <p>Belum ada entri yang cocok.</p>
        </div>
    @else
        <div class="card p-0 overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-text-secondary border-b border-border">
                        @if (!auth()->user()->isMahasiswa())
                            <th class="py-3 px-4">Mahasiswa</th>
                        @endif
                        <th class="py-3 px-4">Sesi</th>
                        <th class="py-3 px-4 table-col-jenis">Jenis</th>
                        <th class="py-3 px-4">Topik</th>
                        <th class="py-3 px-4 table-col-tanggal">Tanggal</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entries as $entry)
                        @php
                            $isMahasiswa = auth()->user()->isMahasiswa();
                            $needsAction = $isMahasiswa && in_array($entry->status, ['draft', 'revisi']);
                        @endphp
                        <tr class="border-b border-border last:border-0 hover:bg-bg-panel/50 {{ $needsAction ? 'bg-status-pending/5' : '' }}">
                            @if (!$isMahasiswa)
                                <td class="py-3 px-4">
                                    {{ $entry->mahasiswaTa?->mahasiswa?->name }}
                                    @if ($entry->mahasiswaTa)
                                        <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded {{ $entry->mahasiswaTa->isKp() ? 'bg-brand/10 text-brand' : 'bg-bg-panel text-text-secondary' }}">{{ $entry->mahasiswaTa->jenisLabel() }}</span>
                                    @endif
                                </td>
                            @endif
                            <td class="py-3 px-4">{{ $entry->jenis === 'revisi' ? '—' : $entry->sesi_ke }}</td>
                            <td class="py-3 px-4 table-col-jenis">{{ ucfirst($entry->jenis) }}</td>
                            <td class="py-3 px-4">{{ $entry->topik ?? 'Revisi' }}</td>
                            <td class="py-3 px-4 table-col-tanggal">{{ $entry->tanggal_tampil?->format('d M Y') ?? '—' }}</td>
                            <td class="py-3 px-4">@include('partials.status-badge', ['status' => $entry->status])</td>
                            <td class="py-3 px-4">
                                <a href="{{ route('logbook.show', $entry) }}" class="text-brand hover:underline">Detail</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-2">{{ $entries->links() }}</div>
    @endif
</div>
@endsection