@extends('layouts.app')

@section('title', 'Kuota Institusi')

@section('content')
<div class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold">Kuota Storage Institusi</h1>
            <p class="text-sm text-text-secondary">Tetapkan kuota pool storage secara langsung per institusi. Kosongkan untuk mengikuti langganan direktori. Nilai terisi (&gt; 0) akan meng-override pool dari langganan.</p>
        </div>
        <a href="{{ route('admin.system.directory-subscriptions') }}" class="inline-flex items-center gap-2 px-3 py-2 rounded-md bg-bg-hover hover:bg-border text-text-primary text-sm font-medium transition-colors">
            <span class="material-symbols-outlined icon-md">workspace_premium</span>
            Langganan Direktori
        </a>
    </div>

    <div class="bg-bg-surface rounded-xl border border-border overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-text-secondary border-b border-border">
                    <th class="py-3 px-4">Institusi</th>
                    <th class="py-3 px-4">Kuota Efektif</th>
                    <th class="py-3 px-4">Dipakai</th>
                    <th class="py-3 px-4">Kuota Langsung (MB)</th>
                    <th class="py-3 px-4">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr class="border-b border-border">
                        <td class="py-3 px-4 font-medium text-text-primary">{{ $row['name'] }}</td>
                        <td class="py-3 px-4">
                            @if ($row['effective_mb'] > 0)
                                <span class="text-text-primary">{{ number_format($row['effective_mb']) }} MB</span>
                                @if ($row['storage_limit_mb'])
                                    <span class="inline-block ml-1 px-1.5 py-0.5 rounded text-[10px] bg-accent-purple/10 text-accent-purple">override</span>
                                @else
                                    <span class="inline-block ml-1 px-1.5 py-0.5 rounded text-[10px] bg-bg-panel">langganan</span>
                                @endif
                            @else
                                <span class="text-text-secondary italic">Tidak ada</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-text-secondary">{{ number_format($row['used_mb']) }} MB</td>
                        <td class="py-3 px-4">
                            <form method="POST" action="{{ route('admin.system.institution-quotas.update', $row['id']) }}" class="inline-flex items-center gap-2">
                                @csrf
                                <input type="number" name="storage_limit_mb" min="0" value="{{ $row['storage_limit_mb'] ?? '' }}" placeholder="Auto"
                                    class="w-36 rounded-md border border-border bg-bg-surface px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                                <button class="px-3 py-1.5 rounded-md bg-brand text-[#0b1420] text-sm font-semibold">Simpan</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-4 px-4 text-text-secondary">Belum ada institusi.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
