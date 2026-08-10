@extends('layouts.app')

@section('title', 'Edit Langganan Direktori')

@section('content')
<div class="max-w-xl space-y-4">
    <div>
        <a href="{{ route('admin.system.directory-subscriptions') }}" class="text-sm text-brand hover:underline">&larr; Kembali ke Langganan Direktori</a>
        <h1 class="text-xl font-bold mt-2">Edit Langganan Direktori</h1>
    </div>

    <div class="bg-bg-surface rounded-xl border border-border p-5">
        @if (session('error'))
            <div class="mb-4 px-4 py-3 rounded-lg bg-status-danger/10 border border-status-danger/30 text-sm text-status-danger">
                {{ session('error') }}
            </div>
        @endif

        <div class="mb-5 p-4 rounded-lg bg-bg-panel border border-border">
            <div class="flex flex-wrap items-center gap-3">
                <span class="inline-block px-2 py-0.5 rounded-full text-[10px] bg-brand-light text-brand">{{ $subscription->scopeLabel() }}</span>
                <span class="text-text-primary font-medium">{{ $subscription->scopeName() }}</span>
                <span class="text-xs text-text-secondary">#{{ $subscription->scope_id }}</span>
            </div>
            <p class="mt-2 text-sm text-text-secondary">Dibuat oleh {{ $subscription->assignedBy?->name ?? '&mdash;' }} pada {{ $subscription->created_at?->format('d M Y H:i') ?? '&mdash;' }}. Scope tidak dapat diubah.</p>
        </div>

        <form method="POST" action="{{ route('admin.system.directory-subscriptions.update', $subscription) }}" class="space-y-3">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm mb-1">Plan</label>
                <select name="plan_id" class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->id }}" @selected(old('plan_id', $subscription->plan_id) == $plan->id)>{{ $plan->label }} ({{ $plan->storageLimitMb() }} MB)</option>
                    @endforeach
                </select>
                @error('plan_id')<p class="text-xs text-status-danger mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm mb-1">Berakhir (opsional)</label>
                <input type="date" name="ends_at" value="{{ old('ends_at', $subscription->ends_at?->format('Y-m-d')) }}" class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                @error('ends_at')<p class="text-xs text-status-danger mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm mb-1">Status</label>
                <select name="status" class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    @foreach (['active' => 'Active', 'expired' => 'Expired', 'cancelled' => 'Cancelled'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $subscription->status) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status')<p class="text-xs text-status-danger mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button class="px-4 py-2 rounded-md bg-brand hover:bg-brand-hover text-[#0b1420] text-sm">Simpan</button>
                <a href="{{ route('admin.system.directory-subscriptions') }}" class="px-4 py-2 rounded-md bg-bg-hover hover:bg-border text-text-primary text-sm">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
