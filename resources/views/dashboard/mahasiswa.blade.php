@extends('layouts.app')

@section('title', 'Dashboard Mahasiswa')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading font-bold text-2xl text-text-primary">Dashboard Mahasiswa</h1>
            <p class="text-sm text-text-secondary mt-0.5">Pantau progres bimbingan Tugas Akhir Anda</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('logbook.create') }}" class="px-4 py-2 rounded-xl bg-accent-blue text-white text-sm font-medium hover:opacity-90">+ Logbook</a>
            <a href="{{ route('logbook.create-revisi') }}" class="px-4 py-2 rounded-xl bg-accent-teal text-white text-sm font-medium hover:opacity-90">+ Entri Revisi</a>
            <a href="{{ route('logbook.index') }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">Semua Entri</a>
        </div>
    </div>

    @if (!$ta)
        <div class="px-4 py-6 rounded-card bg-status-pending/10 text-status-pending border border-status-pending/20 flex items-start gap-2.5">
            <span class="mt-0.5">ℹ️</span><span>Data Tugas Akhir Anda belum diinput oleh admin.</span>
        </div>
    @else
        {{-- ===== Pengumuman belum dibaca (banner) ===== --}}
        @foreach ($unreadAnnouncements as $a)
            <div class="px-4 py-3.5 rounded-card border border-status-pending/30 bg-status-pending/10 flex items-start gap-3">
                <span class="text-xl">📢</span>
                <div class="flex-1 text-sm">
                    <p class="font-semibold text-text-primary">{{ $a->title }}</p>
                    <p class="text-text-secondary">{{ $a->body }}</p>
                    <p class="text-xs text-text-secondary mt-1">Dari: {{ $a->sender?->name }} · {{ $a->created_at?->diffForHumans() }}</p>
                </div>
                <form method="POST" action="{{ route('announcements.read', $a) }}" class="flex-shrink-0">
                    @csrf
                    <button class="px-3 py-1.5 rounded-xl bg-accent-blue text-white text-xs font-medium hover:opacity-90">Tandai Dibaca</button>
                </form>
            </div>
        @endforeach

        {{-- ===== Health indicator (self-awareness) ===== --}}
        <div class="px-4 py-3 rounded-card border flex items-center gap-3
            {{ $regularity === 'green' ? 'bg-status-success/10 border-status-success/20 text-status-success' : '' }}
            {{ $regularity === 'yellow' ? 'bg-status-pending/10 border-status-pending/20 text-status-pending' : '' }}
            {{ $regularity === 'red' ? 'bg-status-danger/10 border-status-danger/20 text-status-danger' : '' }}">
            <span class="inline-block w-4 h-4 rounded-full flex-shrink-0
                {{ $regularity === 'green' ? 'bg-status-success' : '' }}
                {{ $regularity === 'yellow' ? 'bg-status-pending' : '' }}
                {{ $regularity === 'red' ? 'bg-status-danger' : '' }}"></span>
            <div class="text-sm">
                <strong>Status bimbingan Anda: {{ ucfirst($regularity) }}</strong>
                <span class="block text-xs opacity-80">{{ $regularityTooltip }}</span>
            </div>
        </div>

        {{-- ===== Milestone Journey (fase) ===== --}}
        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-heading font-semibold text-text-primary">Milestone Journey</h2>
                <span class="text-sm text-text-secondary">{{ $ta->faseLabel() }} · {{ $progressPercent }}%</span>
            </div>
            @include('partials.milestone', ['faseKeys' => $faseKeys, 'faseIndex' => $faseIndex])
            <p class="mt-3 text-xs text-text-secondary">Fase ditetapkan oleh dosen pembimbing.</p>
        </div>

        {{-- ===== Info TA + Progres ===== --}}
        <div class="grid md:grid-cols-3 gap-5">
            <div class="card p-6 md:col-span-2">
                <h2 class="font-heading font-semibold text-text-primary mb-1">Judul TA</h2>
                <p class="text-text-primary">{{ $ta->judul_ta }}</p>
                <div class="mt-4 grid sm:grid-cols-2 gap-3 text-sm">
                    <div class="px-3 py-2.5 rounded-xl bg-bg-panel">
                        <span class="text-text-secondary">Pembimbing 1:</span>
                        <span class="font-medium text-text-primary">{{ $ta->pembimbing1?->name ?? '—' }}</span>
                    </div>
                    <div class="px-3 py-2.5 rounded-xl bg-bg-panel">
                        <span class="text-text-secondary">Pembimbing 2:</span>
                        <span class="font-medium text-text-primary">{{ $ta->pembimbing2?->name ?? '—' }}</span>
                    </div>
                    <div class="px-3 py-2.5 rounded-xl bg-bg-panel">
                        <span class="text-text-secondary">Penguji 1:</span>
                        <span class="font-medium text-text-primary">{{ $ta->penguji1?->name ?? '—' }}</span>
                    </div>
                    <div class="px-3 py-2.5 rounded-xl bg-bg-panel">
                        <span class="text-text-secondary">Penguji 2:</span>
                        <span class="font-medium text-text-primary">{{ $ta->penguji2?->name ?? '—' }}</span>
                    </div>
                </div>
                <div class="mt-4 flex gap-2">
                    @if ($ta->pembimbing1 || $ta->pembimbing2)
                        <a href="{{ route('logbook.export.pdf', $ta) }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">Rekap PDF</a>
                        <a href="{{ route('logbook.export.excel', $ta) }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">Excel</a>
                    @endif
                </div>
            </div>

            <div class="card p-6">
                <h2 class="font-heading font-semibold text-text-primary mb-3">Progres Bimbingan</h2>
                <div class="flex items-end justify-between mb-1 text-sm">
                    <span class="text-text-secondary">{{ $approved }} / {{ $target }} sesi disetujui</span>
                    <span class="font-bold text-text-primary">{{ $progressPercent }}%</span>
                </div>
                <div class="h-3 rounded-full bg-bg-panel overflow-hidden">
                    <div class="progress-bar h-full rounded-full bg-accent-teal" style="width:{{ $progressPercent }}%"></div>
                </div>
                <p class="mt-3 text-xs text-text-secondary">Minimal {{ $target }} sesi bimbingan perlu disetujui.</p>
            </div>
        </div>

        {{-- ===== Badge + Statistik ===== --}}
        <div class="grid lg:grid-cols-2 gap-5">
            <div class="card p-6">
                <h2 class="font-heading font-semibold text-text-primary mb-3">Achievement ({{ $unlockedAchievements->count() }}/{{ $totalAchievements }})</h2>
                @include('partials.badge-shelf', ['unlockedAchievements' => $unlockedAchievements, 'unlockedCodes' => $unlockedCodes, 'totalAchievements' => $totalAchievements])
            </div>

            <div class="card p-6">
                <h2 class="font-heading font-semibold text-text-primary mb-3">Statistik &amp; Streak</h2>
                @include('partials.stat-cards', ['stats' => $stats])
            </div>
        </div>

        {{-- ===== Heatmap ===== --}}
        <div class="card p-6">
            <h2 class="font-heading font-semibold text-text-primary mb-3">Aktivitas 12 Bulan</h2>
            @include('partials.heatmap', ['heatmap' => $heatmap])
        </div>

        {{-- ===== Timeline ===== --}}
        <div class="card p-6">
            <h2 class="font-heading font-semibold text-text-primary mb-4">Timeline Bimbingan</h2>
            @include('partials.timeline', ['timeline' => $timeline, 'regularity' => $regularity, 'regularityTooltip' => $regularityTooltip])
        </div>
    @endif
</div>
@endsection
