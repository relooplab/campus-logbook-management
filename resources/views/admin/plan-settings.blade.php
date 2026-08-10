@extends('layouts.app')

@section('title', 'Pengaturan Paket')

@section('content')
<div class="max-w-2xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading font-bold text-2xl text-text-primary">Pengaturan Paket</h1>
            <p class="text-sm text-text-secondary mt-0.5">{{ $user->name }} · {{ $user->email }}</p>
        </div>
        <a href="{{ route('admin.users') }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">← Kembali</a>
    </div>

    <div class="card p-6 space-y-4">
        <h2 class="font-heading font-semibold text-text-primary">Paket & Fitur</h2>

        @if ($user->institution_id)
            @if (! empty($institutionHasSubscription))
                <div class="px-4 py-3 rounded-xl bg-status-success/10 border border-status-success/30 text-sm text-status-success">
                    ✅ Institusi user ini memiliki langganan direktori aktif. Kuota individu di bawah ini adalah <strong>override per-user</strong> di atas pool institusi.
                </div>
            @else
                <div class="px-4 py-3 rounded-xl bg-status-pending/10 border border-status-pending/30 text-sm text-status-pending">
                    ⚠️ Institusi user ini <strong>belum berlangganan</strong>. Anda tetap bisa mengatur kuota individu (override &ldquo;Batas Workspace&rdquo; di bawah) yang akan berlaku efektif. Kuota &ldquo;Batas Per-User dalam Pool Institusi&rdquo; tidak efektif tanpa langganan.
                </div>
            @endif
        @else
            <div class="px-4 py-3 rounded-xl bg-bg-panel border border-border text-sm text-text-secondary">
                User ini adalah <strong>Personal</strong> (tanpa institusi). Kuota individu (override &ldquo;Batas Workspace&rdquo; di bawah) yang berlaku efektif.
            </div>
        @endif

        <form method="POST" action="{{ route('admin.users.plan.update', $user) }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs text-text-secondary mb-1">Paket</label>
                <select name="plan_id" class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->id }}" @selected($activePlan?->id === $plan->id)>
                            {{ $plan->label }} ({{ $plan->feature('export') ? 'Export+Import' : 'Dasar' }}) — {{ $plan->storageLimitMb() }} MB
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="border-t border-border pt-4">
                <p class="text-sm font-medium text-text-primary mb-2">Override Admin (custom per individu)</p>
                <p class="text-xs text-text-secondary mb-3">Kosongkan untuk mengikuti paket. Centang untuk mengaktifkan.</p>

                <div class="space-y-3">
                    <label class="flex items-center justify-between gap-3 p-3 rounded-xl bg-bg-panel border border-border">
                        <div>
                            <p class="text-sm font-medium text-text-primary">Export</p>
                            <p class="text-xs text-text-secondary">Izinkan export PDF/Excel</p>
                        </div>
                        <input type="checkbox" name="allow_export" value="1" @checked($override?->allow_export) class="rounded bg-bg-surface">
                    </label>

                    <label class="flex items-center justify-between gap-3 p-3 rounded-xl bg-bg-panel border border-border">
                        <div>
                            <p class="text-sm font-medium text-text-primary">Import</p>
                            <p class="text-xs text-text-secondary">Izinkan import massal</p>
                        </div>
                        <input type="checkbox" name="allow_import" value="1" @checked($override?->allow_import) class="rounded bg-bg-surface">
                    </label>

                    <div>
                        <label class="block text-xs text-text-secondary mb-1">Batas Workspace (MB)</label>
                        <input type="number" name="storage_limit_mb" value="{{ $override?->storage_limit_mb ?? '' }}" min="0" placeholder="Kosongkan = ikut paket"
                            class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                    </div>

                    @if ($user->institution_id)
                        <div>
                            <label class="block text-xs text-text-secondary mb-1">Batas Per-User dalam Pool Institusi (MB)</label>
                            <input type="number" name="institution_storage_limit_mb" value="{{ $user->institution_storage_limit_mb ?? '' }}" min="0" placeholder="Kosongkan = unlimited dalam pool"
                                class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                            <p class="text-xs text-text-secondary mt-1">Efektif = min(pool institusi, batas per-user).</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="px-4 py-2 rounded-xl bg-brand text-[#0b1420] text-sm font-medium hover:opacity-90">Simpan Paket</button>
            </div>
        </form>
    </div>
</div>
@endsection