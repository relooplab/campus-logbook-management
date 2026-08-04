@extends('layouts.app')

@section('title', 'Workspace')

@section('content')
@php
    $isDosen = $user->isDosen();
    $isAdmin = $user->isAdmin();
    $allPersonalFiles = $personalFiles ?? collect();
    $personalBabs = $allPersonalFiles->pluck('bab')->filter()->unique();
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading font-bold text-2xl text-text-primary"><span class="material-symbols-outlined icon-md align-text-bottom">folder</span> Workspace</h1>
            <p class="text-sm text-text-secondary mt-0.5">
                @if ($isDosen)
                    File pribadi & workspace bimbingan Anda
                @elseif ($isAdmin)
                    Semua workspace TA/KP
                @else
                    Workspace program Anda
                @endif
            </p>
        </div>
        <a href="{{ route('dashboard') }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">← Dashboard</a>
    </div>

    {{-- ===== Workspace Pribadi (dosen) ===== --}}
    @if ($isDosen)
        <div class="card p-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div>
                    <h2 class="font-heading font-semibold text-text-primary">Workspace Pribadi</h2>
                    <p class="text-sm text-text-secondary">File pribadi milik Anda — hanya Anda yang bisa melihat</p>
                </div>
                <span class="text-xs text-text-secondary">{{ number_format($personalTotalBytes / 1048576, 1) }} MB terpakai</span>
            </div>

            {{-- Upload --}}
            <form method="POST" action="{{ route('workspace.personal-store') }}" enctype="multipart/form-data" class="mb-4">
                @csrf
                <div class="grid sm:grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-xs text-text-secondary mb-1">Label Bab (opsional)</label>
                        <input type="text" name="bab" placeholder="contoh: Materi, Jurnal, Arsip"
                            class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <input type="file" name="files[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx" class="text-sm">
                    <button type="submit" class="px-4 py-2 rounded-xl bg-brand text-white text-sm font-medium hover:opacity-90">Upload</button>
                </div>
                <p class="text-xs text-text-secondary mt-1">PDF, DOC, DOCX, XLS, XLSX — maks 25 MB, hingga 5 file</p>
                @error('files.*')
                    <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                @enderror
            </form>

            {{-- Daftar file pribadi --}}
            @if ($allPersonalFiles->isEmpty())
                <div class="px-4 py-8 rounded-xl bg-bg-panel border border-border text-center text-text-secondary">
                    <span class="material-symbols-outlined icon-lg mb-2 text-text-secondary/50">folder_off</span>
                    <p>Belum ada file di workspace pribadi Anda.</p>
                </div>
            @else
                <div class="space-y-2">
                    @foreach ($personalGrouped as $bab => $files)
                        <div class="card p-0 overflow-hidden">
                            <div class="px-4 py-2.5 bg-bg-panel/50 border-b border-border font-semibold text-sm text-text-primary">
                                {{ $bab }}</div>
                            <div class="divide-y divide-border">
                                @foreach ($files as $file)
                                    <div class="px-4 py-2.5 flex items-start gap-3">
                                        <span class="text-2xl">{{ $file->icon() }}</span>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <a href="{{ $file->isPdf() ? route('workspace.preview', $file) : route('workspace.download', $file) }}"
                                                    class="font-medium hover:text-brand" @if ($file->isPdf()) target="_blank" @endif>
                                                    {{ $file->original_name }}
                                                </a>
                                                <span class="text-xs text-text-secondary">{{ $file->sizeHuman() }}</span>
                                                <span class="text-xs text-text-secondary">{{ $file->created_at->format('d M') }}</span>
                                            </div>
                                            @if ($file->description)
                                                <p class="text-xs text-text-secondary mt-0.5">"{{ $file->description }}"</p>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-1 flex-shrink-0">
                                            @if ($file->isPdf())
                                                <a href="{{ route('workspace.preview', $file) }}" target="_blank" title="Preview"
                                                    class="p-1.5 rounded hover:bg-bg-hover"><span class="material-symbols-outlined icon-sm">visibility</span></a>
                                            @endif
                                            <a href="{{ route('workspace.download', $file) }}" title="Download"
                                                class="p-1.5 rounded hover:bg-bg-hover"><span class="material-symbols-outlined icon-sm">download</span></a>
                                            <form method="POST" action="{{ route('workspace.destroy', $file) }}"
                                                onsubmit="return confirm('Hapus file ini?')">
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
                </div>
            @endif
        </div>
    @endif

    {{-- ===== Daftar Workspace TA/KP (dosen & admin) ===== --}}
    <div class="card p-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <h2 class="font-heading font-semibold text-text-primary">
                    {{ $isDosen ? 'Workspace Bimbingan' : 'Semua Workspace' }}
                </h2>
                <p class="text-sm text-text-secondary">
                    {{ $isDosen ? 'TA/KP di mana Anda menjadi pembimbing atau penguji' : 'Semua program TA/KP di sistem' }}
                </p>
            </div>
            <span class="text-xs text-text-secondary">{{ $tas->count() }} program</span>
        </div>

        @if ($tas->isEmpty())
            <div class="px-4 py-8 rounded-xl bg-bg-panel border border-border text-center text-text-secondary">
                <span class="material-symbols-outlined icon-lg mb-2 text-text-secondary/50">folder_off</span>
                <p>{{ $isDosen ? 'Belum ada mahasiswa bimbingan.' : 'Belum ada program TA/KP.' }}</p>
            </div>
        @else
            <div class="space-y-2">
                @foreach ($tas as $ta)
                    <a href="{{ route('workspace.index', $ta) }}" class="flex items-center gap-3 p-3 rounded-xl bg-bg-panel border border-border hover:border-brand/30 transition-colors">
                        <span class="icon-circle w-10 h-10 bg-brand-light text-brand">
                            <span class="material-symbols-outlined icon-md">folder</span>
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-text-primary">{{ $ta->mahasiswa?->name }} <span class="text-xs text-text-secondary">({{ $ta->jenisLabel() }})</span></p>
                            <p class="text-xs text-text-secondary truncate">{{ $ta->isKp() ? ($ta->tempat_kp ?: '—') : $ta->judul_ta }}</p>
                        </div>
                        <span class="text-brand text-xs font-medium">Buka →</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection