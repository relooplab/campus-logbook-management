@extends('layouts.app')

@section('title', 'Review Finalisasi')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading font-bold text-2xl text-text-primary">Review Finalisasi</h1>
            <p class="text-sm text-text-secondary mt-0.5">Setujui/tolak item finalisasi mahasiswa</p>
        </div>
        <a href="{{ route('dashboard') }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">← Dashboard</a>
    </div>

    @if ($finalizations->isEmpty())
        <div class="px-4 py-10 rounded-xl bg-bg-panel border border-border text-center text-text-secondary">Belum ada finalisasi.</div>
    @else
        @foreach ($finalizations as $f)
            @php $ta = $f->mahasiswaTa; @endphp
            <div class="card p-6">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div>
                        <p class="font-medium text-text-primary">{{ $ta?->mahasiswa?->name }}</p>
                        <p class="text-xs text-text-secondary">{{ $ta?->jenisLabel() }} · {{ $ta?->judul_ta ?: $ta?->tempat_kp }}</p>
                    </div>
                    @if ($f->nilai)
                        <span class="text-sm font-bold text-brand">Nilai: {{ number_format($f->nilai, 2) }}</span>
                    @endif
                </div>

                @php
                    $items = $ta?->isKp() ? ['full_file' => 'Laporan Lengkap'] : [
                        'abstrak' => 'Abstrak', 'keyword' => 'Keyword', 'cover' => 'File Cover',
                        'pengesahan' => 'Lembar Pengesahan', 'full_file' => 'File Lengkap',
                    ];
                @endphp
                <div class="space-y-2">
                    @foreach ($items as $key => $label)
                        <div class="flex flex-wrap items-center justify-between gap-2 p-3 rounded-xl bg-bg-panel border border-border">
                            <span class="text-sm font-medium">{{ $label }}</span>
                            <div class="flex items-center gap-2">
                                <span class="text-xs px-2 py-0.5 rounded-full {{ $f->{$key.'_status'} === 'approved' ? 'bg-status-success/10 text-status-success' : ($f->{$key.'_status'} === 'submitted' ? 'bg-status-pending/10 text-status-pending' : 'bg-bg-hover text-text-secondary') }}">
                                    {{ ucfirst($f->{$key.'_status'}) }}
                                </span>
                                @if ($f->{$key.'_status'} === 'submitted' || $f->{$key.'_status'} === 'approved')
                                    <form method="POST" action="{{ route('finalization.approve', [$f, $key]) }}">@csrf
                                        <button class="px-2 py-1 rounded-lg bg-status-success/10 text-status-success text-xs">Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('finalization.reject', [$f, $key]) }}" class="flex flex-col gap-1 items-end"
                                        onsubmit="return confirm('Tolak item ini? Alasan akan wajib diisi.')">
                                        @csrf
                                        <input type="text" name="alasan" required minlength="5" maxlength="1000" placeholder="Alasan penolakan (min. 5 karakter)..."
                                            class="w-48 rounded-lg border border-border bg-bg-surface px-2 py-1 text-xs">
                                        <button class="px-2 py-1 rounded-lg bg-status-danger/10 text-status-danger text-xs">Tolak</button>
                                    </form>
                                @endif
                                @if ($f->{$key.'_status'} === 'approved')
                                    <form method="POST" action="{{ route('finalization.unlock', [$f, $key]) }}">@csrf
                                        <button class="px-2 py-1 rounded-lg bg-bg-hover text-text-secondary text-xs">Unlock</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <form method="POST" action="{{ route('finalization.nilai', $f) }}" class="mt-4 flex items-end gap-2">
                    @csrf
                    <div>
                        <label class="block text-xs text-text-secondary mb-1">Nilai Akhir</label>
                        <input type="number" name="nilai" min="0" max="100" step="0.01" value="{{ $f->nilai }}" class="w-32 rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm">
                    </div>
                    <button class="px-4 py-2 rounded-xl bg-brand text-white text-sm font-medium hover:opacity-90">Simpan Nilai</button>
                </form>
            </div>
        @endforeach
    @endif
</div>
@endsection