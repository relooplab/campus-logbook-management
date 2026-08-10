@extends('layouts.app')

@section('title', 'Kelola Hak Akses')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading font-bold text-2xl text-text-primary"><span class="material-symbols-outlined icon-md align-text-bottom">admin_panel_settings</span> Kelola Hak Akses</h1>
            <p class="text-sm text-text-secondary mt-0.5">Atur permission per role & fitur paket</p>
        </div>
        <a href="{{ route('dashboard') }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">← Dashboard</a>
    </div>

    {{-- ===== Matrix Permission per Role ===== --}}
    <div class="card p-6">
        <h2 class="font-heading font-semibold text-text-primary mb-1">Matrix Permission per Role</h2>
        <p class="text-sm text-text-secondary mb-4">Centang permission yang dimiliki setiap role. Perubahan berlaku global untuk semua user dengan role tersebut.</p>

        <form method="POST" action="{{ route('admin.system.permissions.update') }}">
            @csrf
            <div class="overflow-x-auto">
                <table class="w-full text-sm border border-border">
                    <thead>
                        <tr class="bg-bg-panel text-left text-text-secondary">
                            <th class="py-2 px-3 border-b border-border">Permission</th>
                            @foreach ($roles as $role)
                                <th class="py-2 px-3 border-b border-border text-center">{{ $role->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @php $currentGroup = null; @endphp
                        @foreach ($permissions as $permission)
                            @php
                                $group = explode('.', $permission->name)[0];
                                $isNewGroup = $group !== $currentGroup;
                                $currentGroup = $group;
                            @endphp
                            @if ($isNewGroup)
                                <tr class="bg-bg-panel/50">
                                    <td colspan="{{ $roles->count() + 1 }}" class="py-1.5 px-3 text-[10px] uppercase tracking-widest text-text-secondary font-semibold">{{ $group }}</td>
                                </tr>
                            @endif
                            <tr class="border-b border-border">
                                <td class="py-1.5 px-3">{{ $permission->name }}</td>
                                @foreach ($roles as $role)
                                    <td class="py-1.5 px-3 text-center">
                                        <input type="checkbox" name="permissions[{{ $role->id }}][]" value="{{ $permission->name }}"
                                            @checked($role->hasPermissionTo($permission->name))
                                            class="rounded bg-bg-surface border-border">
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                <button type="submit" class="px-4 py-2 rounded-xl bg-brand text-[#0b1420] text-sm font-medium hover:opacity-90">Simpan Hak Akses</button>
            </div>
        </form>
    </div>

    {{-- ===== Pengaturan Fitur Paket ===== --}}
    <div class="card p-6">
        <h2 class="font-heading font-semibold text-text-primary mb-1">Pengaturan Fitur Paket</h2>
        <p class="text-sm text-text-secondary mb-4">Atur batas penyimpanan & fitur export/import per paket.</p>

        <form method="POST" action="{{ route('admin.system.plans.update') }}">
            @csrf
            <div class="space-y-4">
                @foreach ($plans as $plan)
                    <div class="rounded-xl border border-border bg-bg-panel p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                            <span class="font-semibold text-text-primary">{{ $plan->name }}</span>
                            <span class="text-xs text-text-secondary">{{ $plan->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                        </div>
                        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            <div>
                                <label class="block text-xs text-text-secondary mb-1">Label</label>
                                <input type="text" name="plans[{{ $plan->id }}][label]" value="{{ $plan->label }}"
                                    class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-text-secondary mb-1">Harga (Rp)</label>
                                <input type="number" name="plans[{{ $plan->id }}][price]" value="{{ $plan->price }}" min="0"
                                    class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-text-secondary mb-1">Storage (MB)</label>
                                <input type="number" name="plans[{{ $plan->id }}][storage_mb]" value="{{ $plan->storageLimitMb() }}" min="0"
                                    class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                            </div>
                            <div class="flex items-end gap-4 pb-1">
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="plans[{{ $plan->id }}][export]" value="1" @checked($plan->feature('export', false))
                                        class="rounded bg-bg-surface border-border"> Export
                                </label>
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="plans[{{ $plan->id }}][import]" value="1" @checked($plan->feature('import', false))
                                        class="rounded bg-bg-surface border-border"> Import
                                </label>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-4">
                <button type="submit" class="px-4 py-2 rounded-xl bg-brand text-[#0b1420] text-sm font-medium hover:opacity-90">Simpan Paket</button>
            </div>
        </form>
    </div>
</div>
@endsection