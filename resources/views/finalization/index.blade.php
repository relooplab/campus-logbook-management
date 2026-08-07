@extends('layouts.app')

@section('title', 'Finalisasi '.$mahasiswaTa->jenisLabel())

@section('content')
<div class="max-w-2xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading font-bold text-2xl text-text-primary">Finalisasi {{ $mahasiswaTa->jenisLabel() }}</h1>
            <p class="text-sm text-text-secondary mt-0.5">Lengkapi informasi akhir untuk menyelesaikan program</p>
        </div>
        <a href="{{ route('dashboard') }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">← Dashboard</a>
    </div>

    @if ($finalization->nilai)
        <div class="card p-6">
            <h2 class="font-heading font-semibold text-text-primary mb-2">Nilai Akhir</h2>
            <p class="font-heading font-bold text-3xl text-brand">{{ number_format($finalization->nilai, 2) }}</p>
        </div>
    @endif

    <div class="card p-6">
        <h2 class="font-heading font-semibold text-text-primary mb-4">Status Item</h2>
        @php
            $items = $mahasiswaTa->isKp() ? ['full_file' => 'Laporan Lengkap'] : [
                'abstrak' => 'Abstrak', 'keyword' => 'Keyword', 'cover' => 'File Cover',
                'pengesahan' => 'Lembar Pengesahan', 'full_file' => 'File Lengkap',
            ];
        @endphp
        <div class="space-y-2">
            @foreach ($items as $key => $label)
                <div class="p-3 rounded-xl bg-bg-panel border border-border">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium">{{ $label }}</span>
                        <span class="text-xs px-2 py-0.5 rounded-full {{ $finalization->{$key.'_status'} === 'approved' ? 'bg-status-success/10 text-status-success' : ($finalization->{$key.'_status'} === 'submitted' ? 'bg-status-pending/10 text-status-pending' : 'bg-bg-hover text-text-secondary') }}">
                            {{ ucfirst($finalization->{$key.'_status'}) }}
                        </span>
                    </div>
                    @php
                        $rejected = $finalization->approvals->where('item', $key)->where('status', 'rejected');
                    @endphp
                    @if ($rejected->isNotEmpty())
                        <div class="mt-2 space-y-1">
                            <p class="text-xs font-semibold text-status-danger">Ditolak dengan alasan:</p>
                            @foreach ($rejected as $ap)
                                <div class="px-2 py-1.5 rounded-lg bg-status-danger/10 text-xs text-status-danger">
                                    @if ($ap->pembimbing)<span class="font-medium">{{ $ap->pembimbing->name }}:</span>@endif
                                    <span class="italic">"{{ $ap->alasan }}"</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    @if ($finalization->allItemsApproved())
        <div class="px-4 py-3 rounded-xl bg-status-success/10 text-status-success border border-status-success/20">
            ✅ Semua item disetujui. Program Anda telah selesai.
        </div>
    @else
        <div class="card p-6">
            <h2 class="font-heading font-semibold text-text-primary mb-4">Isi & Kirim Finalisasi</h2>
            <form method="POST" action="{{ route('finalization.store', $mahasiswaTa) }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                @if (!$mahasiswaTa->isKp())
                    <div>
                        <label class="block text-xs text-text-secondary mb-1">Abstrak</label>
                        <textarea name="abstrak" rows="4" required class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm">{{ old('abstrak', $finalization->abstrak) }}</textarea>
                    </div>
                    <div>
                        <label class="block text-xs text-text-secondary mb-1">Keyword</label>
                        <input type="text" name="keyword" required value="{{ old('keyword', $finalization->keyword) }}" class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-text-secondary mb-1">File Cover (PDF)</label>
                        <input type="file" name="cover" accept="application/pdf" required class="w-full text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-text-secondary mb-1">Lembar Pengesahan (PDF)</label>
                        <input type="file" name="pengesahan" accept="application/pdf" required class="w-full text-sm">
                    </div>
                @endif
                <div>
                    <label class="block text-xs text-text-secondary mb-1">File Lengkap (PDF, awal-akhir)</label>
                    <input type="file" name="full_file" accept="application/pdf" required class="w-full text-sm">
                </div>
                <button type="submit" class="px-4 py-2 rounded-xl bg-brand text-white text-sm font-medium hover:opacity-90">Kirim Finalisasi</button>
            </form>
        </div>
    @endif
</div>
@endsection