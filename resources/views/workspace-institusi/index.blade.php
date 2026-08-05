@extends('layouts.app')

@section('title', 'Workspace Institusi')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold">Workspace Institusi</h1>
            <p class="text-sm text-text-secondary">Workspace berbagi file di level universitas/fakultas/departemen/prodi.</p>
        </div>
        @if ($user->isAdmin())
            <a href="#buat" class="px-4 py-2 rounded-xl bg-brand text-white text-sm font-medium">+ Buat Workspace</a>
        @endif
    </div>

    @if ($user->isAdmin())
        <div id="buat" class="bg-bg-surface rounded-xl border border-border p-5">
            <h2 class="font-semibold mb-3">Buat Workspace Baru</h2>
            <form method="POST" action="{{ route('workspace-institusi.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-sm mb-1">Level / Simpul</label>
                    <select name="scope_type" class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                        <option value="university">Universitas</option>
                        <option value="faculty">Fakultas</option>
                        <option value="department">Departemen</option>
                        <option value="study_program">Program Studi</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm mb-1">ID Simpul</label>
                    <input type="number" name="scope_id" required class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    <p class="text-xs text-text-secondary mt-1">ID universitas/fakultas/departemen/prodi di direktori.</p>
                </div>
                <div>
                    <label class="block text-sm mb-1">Nama Workspace</label>
                    <input type="text" name="name" required class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm mb-1">Mode Akses</label>
                    <select name="access_mode" class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                        <option value="hierarchical">Sesama prodi (default)</option>
                        <option value="custom">Custom (dosen tertentu)</option>
                    </select>
                </div>
                <button class="w-full px-3 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm">Buat</button>
            </form>
        </div>
    @endif

    @php
        $labels = [
            'university' => 'Universitas',
            'faculty' => 'Fakultas',
            'department' => 'Departemen',
            'study_program' => 'Program Studi',
        ];
    @endphp

    @foreach ($labels as $key => $label)
        @if ($grouped->has($key))
            <div>
                <h2 class="font-semibold text-sm text-text-secondary uppercase tracking-wider mb-2">{{ $label }}</h2>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($grouped->get($key) as $ws)
                        <a href="{{ route('workspace-institusi.show', $ws) }}" class="bg-bg-surface rounded-xl border border-border p-4 hover:border-brand/50 transition">
                            <div class="flex items-center justify-between">
                                <span class="material-symbols-outlined icon-lg text-brand">folder_shared</span>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-bg-panel text-text-secondary">{{ $ws->scopeLabel() }}</span>
                            </div>
                            <p class="font-semibold mt-2">{{ $ws->name }}</p>
                            <p class="text-xs text-text-secondary">{{ $ws->scopeName() }}</p>
                            <p class="text-xs text-text-secondary mt-1">{{ $ws->files()->count() }} file</p>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach

    @if ($grouped->isEmpty())
        <div class="bg-bg-surface rounded-xl border border-border p-8 text-center text-text-secondary">
            Tidak ada workspace institusi yang dapat Anda akses.
        </div>
    @endif
</div>
@endsection