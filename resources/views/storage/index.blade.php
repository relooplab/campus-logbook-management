@extends('layouts.app')

@section('title', 'Workspace Mahasiswa')

@section('content')
@php
    $allWorkspace = $workspaceFiles->groupBy(fn ($f) => $f->mahasiswaTa?->mahasiswa?->name ?? 'Tanpa Mahasiswa');
    $allLogbook = $logbookHarian->groupBy(fn ($e) => $e->mahasiswaTa?->mahasiswa?->name ?? 'Tanpa Mahasiswa');
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading font-bold text-2xl text-text-primary"><span class="material-symbols-outlined icon-md align-text-bottom">database</span> Workspace Mahasiswa</h1>
            <p class="text-sm text-text-secondary mt-0.5">File workspace & foto logbook mahasiswa bimbingan yang dibebankan ke kuota Anda</p>
        </div>
        <a href="{{ route('dashboard') }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">← Dashboard</a>
    </div>

    {{-- Ringkasan kuota --}}
    <div class="card p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-heading font-semibold text-text-primary">Kuota Workspace</h2>
            <span class="text-sm text-text-secondary">{{ $usedLabel }} / {{ $limitLabel }}</span>
        </div>
        @if ($limitBytes > 0)
            <div class="mt-2 h-2 rounded-full bg-bg-panel overflow-hidden">
                <div class="h-full rounded-full {{ $pct >= 90 ? 'bg-status-danger' : ($pct >= 70 ? 'bg-status-pending' : 'bg-brand') }}" style="width: {{ $pct }}%"></div>
            </div>
            <p class="text-xs text-text-secondary mt-1">{{ $pct }}% terpakai</p>
        @endif
    </div>

    {{-- Workspace files --}}
    <div class="card p-6">
        <h2 class="font-heading font-semibold text-text-primary mb-3">File Workspace Mahasiswa</h2>
        @if ($workspaceFiles->isEmpty())
            <div class="px-4 py-8 rounded-xl bg-bg-panel border border-border text-center text-text-secondary">
                <span class="material-symbols-outlined icon-lg mb-2 text-text-secondary/50">folder_off</span>
                <p>Belum ada file workspace dari mahasiswa bimbingan.</p>
            </div>
        @else
            @foreach ($allWorkspace as $mahasiswaName => $files)
                <div class="mb-4">
                    <p class="text-sm font-semibold text-text-primary mb-2">{{ $mahasiswaName }}</p>
                    <div class="divide-y divide-border border border-border rounded-xl overflow-hidden">
                        @foreach ($files as $file)
                            <div class="px-4 py-2.5 flex items-start gap-3 bg-bg-surface">
                                <span class="text-2xl">{{ $file->icon() }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-text-primary">{{ $file->original_name }}</span>
                                        <span class="text-xs text-text-secondary">{{ $file->sizeHuman() }}</span>
                                        <span class="text-xs text-text-secondary">{{ $file->created_at->format('d M Y') }}</span>
                                    </div>
                                    @if ($file->description)
                                        <p class="text-xs text-text-secondary mt-0.5">"{{ $file->description }}"</p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1 flex-shrink-0">
                                    <a href="{{ $file->isPdf() ? route('workspace.preview', $file) : route('workspace.download', $file) }}" target="_blank" title="Buka"
                                        class="p-1.5 rounded hover:bg-bg-hover"><span class="material-symbols-outlined icon-sm">open_in_new</span></a>
                                    <form method="POST" action="{{ route('storage.destroy-workspace', $file) }}"
                                        onsubmit="return confirm('Hapus file workspace ini? Mahasiswa akan kehilangan akses ke file ini.')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="p-1.5 rounded hover:bg-status-danger/10 text-status-danger" title="Hapus">
                                            <span class="material-symbols-outlined icon-sm">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    {{-- Logbook harian foto --}}
    <div class="card p-6">
        <h2 class="font-heading font-semibold text-text-primary mb-3">Foto Logbook Harian KP</h2>
        @if ($logbookHarian->isEmpty())
            <div class="px-4 py-8 rounded-xl bg-bg-panel border border-border text-center text-text-secondary">
                <span class="material-symbols-outlined icon-lg mb-2 text-text-secondary/50">photo_library</span>
                <p>Belum ada foto logbook harian dari mahasiswa bimbingan.</p>
            </div>
        @else
            @foreach ($allLogbook as $mahasiswaName => $entries)
                <div class="mb-4">
                    <p class="text-sm font-semibold text-text-primary mb-2">{{ $mahasiswaName }}</p>
                    <div class="divide-y divide-border border border-border rounded-xl overflow-hidden">
                        @foreach ($entries as $entry)
                            @php
                                $entryTa = $entry->mahasiswaTa;
                                $fotos = collect([1 => $entry->foto_1, 2 => $entry->foto_2])->filter();
                            @endphp
                            @foreach ($fotos as $idx => $path)
                                <div class="px-4 py-2.5 flex items-start gap-3 bg-bg-surface">
                                    <span class="material-symbols-outlined text-2xl text-text-secondary">photo</span>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium text-text-primary">Foto {{ $idx }} — {{ $entry->tanggal->format('d M Y') }}</p>
                                        <p class="text-xs text-text-secondary">{{ $entry->kegiatan }}</p>
                                    </div>
                                    <div class="flex items-center gap-1 flex-shrink-0">
                                        <a href="{{ route('logbook-harian.foto', [$entryTa, $entry, $idx]) }}" target="_blank" title="Buka"
                                            class="p-1.5 rounded hover:bg-bg-hover"><span class="material-symbols-outlined icon-sm">open_in_new</span></a>
                                        <form method="POST" action="{{ route('storage.destroy-logbook-harian', [$entry, 'foto_'.$idx]) }}"
                                            onsubmit="return confirm('Hapus foto logbook harian ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="p-1.5 rounded hover:bg-status-danger/10 text-status-danger" title="Hapus">
                                                <span class="material-symbols-outlined icon-sm">delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>
@endsection