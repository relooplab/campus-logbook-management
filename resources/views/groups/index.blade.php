@extends('layouts.app')

@section('title', 'Grup Dosen')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading font-bold text-2xl text-text-primary">Grup Dosen</h1>
            <p class="text-sm text-text-secondary mt-0.5">Kolaborasi & cross-link antar dosen di universitas yang sama</p>
        </div>
        <a href="{{ route('dashboard') }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">← Dashboard</a>
    </div>

    {{-- Undangan pending --}}
    @if ($pendingInvites->isNotEmpty())
        <div class="card p-6">
            <h2 class="font-heading font-semibold text-text-primary mb-4">Undangan Menunggu ({{ $pendingInvites->count() }})</h2>
            <div class="space-y-3">
                @foreach ($pendingInvites as $group)
                    <div class="flex flex-wrap items-center justify-between gap-3 p-4 rounded-xl bg-bg-panel border border-border">
                        <div>
                            <p class="font-medium text-text-primary">{{ $group->name }}</p>
                            <p class="text-xs text-text-secondary">Dari: {{ $group->creator?->name }} · {{ $group->university?->name }}</p>
                        </div>
                        <div class="flex gap-2">
                            <form method="POST" action="{{ route('groups.approve', $group) }}">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 rounded-xl bg-brand text-white text-xs font-medium hover:opacity-90">Terima</button>
                            </form>
                            <form method="POST" action="{{ route('groups.reject', $group) }}">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 rounded-xl bg-status-danger/10 text-status-danger text-xs font-medium hover:bg-status-danger/20">Tolak</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Buat grup baru --}}
    <div class="card p-6">
        <h2 class="font-heading font-semibold text-text-primary mb-4">Buat Grup Baru</h2>
        @if (!$university)
            <p class="text-sm text-text-secondary">Lengkapi profil universitas Anda terlebih dahulu untuk membuat grup.</p>
        @else
            <form method="POST" action="{{ route('groups.store') }}" class="grid sm:grid-cols-2 gap-3">
                @csrf
                <input type="hidden" name="university_id" value="{{ $university->id }}">
                <div class="sm:col-span-2">
                    <label class="block text-xs text-text-secondary mb-1">Nama Grup</label>
                    <input type="text" name="name" required placeholder="Contoh: Dosen Teknik Informatika Universitas X"
                        class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                </div>
                <div>
                    <label class="block text-xs text-text-secondary mb-1">Level</label>
                    <select name="level" class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                        <option value="universitas">Universitas</option>
                        <option value="fakultas">Fakultas</option>
                        <option value="departemen">Departemen</option>
                        <option value="prodi">Program Studi</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full px-4 py-2 rounded-xl bg-brand text-white text-sm font-medium hover:opacity-90">Buat Grup</button>
                </div>
            </form>
        @endif
    </div>

    {{-- Grup yang saya ikuti --}}
    <div class="card p-6">
        <h2 class="font-heading font-semibold text-text-primary mb-4">Grup Saya ({{ $myGroups->count() }})</h2>
        @if ($myGroups->isEmpty())
            <p class="text-sm text-text-secondary">Belum bergabung dengan grup mana pun.</p>
        @else
            <div class="space-y-4">
                @foreach ($myGroups as $group)
                    <div class="p-4 rounded-xl bg-bg-panel border border-border">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                            <div>
                                <p class="font-medium text-text-primary">{{ $group->name }}</p>
                                <p class="text-xs text-text-secondary">{{ ucfirst($group->level) }} · {{ $group->university?->name }}</p>
                            </div>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-status-success/10 text-status-success">
                                <span class="w-1.5 h-1.5 rounded-full bg-status-success"></span> {{ $group->members->count() }} anggota
                            </span>
                        </div>

                        {{-- Anggota --}}
                        <div class="flex flex-wrap gap-1.5 mb-3">
                            @foreach ($group->members as $member)
                                <span class="inline-block px-2 py-0.5 rounded-full text-xs bg-bg-surface border border-border">{{ $member->name }}</span>
                            @endforeach
                        </div>

                        {{-- Undang dosen --}}
                        @if ($colleagues->isNotEmpty())
                            <form method="POST" action="{{ route('groups.invite', $group) }}" class="flex flex-col sm:flex-row gap-2">
                                @csrf
                                <select name="user_id" class="flex-1 rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                                    <option value="">Pilih dosen untuk diundang…</option>
                                    @foreach ($colleagues as $colleague)
                                        <option value="{{ $colleague->id }}">{{ $colleague->name }} ({{ $colleague->nidn ?: '—' }})</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">Undang</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Grup tersedia di universitas --}}
    @if ($availableGroups->isNotEmpty())
        <div class="card p-6">
            <h2 class="font-heading font-semibold text-text-primary mb-4">Grup Tersedia di {{ $university?->name }}</h2>
            <div class="space-y-3">
                @foreach ($availableGroups as $group)
                    <div class="flex flex-wrap items-center justify-between gap-3 p-4 rounded-xl bg-bg-panel border border-border">
                        <div>
                            <p class="font-medium text-text-primary">{{ $group->name }}</p>
                            <p class="text-xs text-text-secondary">{{ ucfirst($group->level) }} · {{ $group->members->count() }} anggota</p>
                        </div>
                        <form method="POST" action="{{ route('groups.invite', $group) }}">
                            @csrf
                            <input type="hidden" name="user_id" value="{{ auth()->id() }}">
                            <button type="submit" class="px-3 py-1.5 rounded-xl bg-brand text-white text-xs font-medium hover:opacity-90">Gabung</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection