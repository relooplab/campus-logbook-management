@extends('layouts.app')

@section('title', 'Edit Universitas')

@section('content')
<div class="max-w-lg space-y-4">
    <div>
        <a href="{{ route('admin.system.directory') }}" class="text-sm text-brand hover:underline">&larr; Kembali ke Direktori</a>
        <h1 class="text-xl font-bold mt-2">Edit Universitas</h1>
    </div>

    <div class="bg-bg-surface rounded-xl border border-border p-5">
        @if (session('error'))
            <div class="mb-4 px-4 py-3 rounded-lg bg-status-danger/10 border border-status-danger/30 text-sm text-status-danger">
                {{ session('error') }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.system.directory.universities.update', $university) }}" class="space-y-3">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm mb-1">Nama Universitas</label>
                <input type="text" name="name" value="{{ old('name', $university->name) }}" maxlength="255" required
                    class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                @error('name')<p class="text-xs text-status-danger mt-1">{{ $message }}</p>@enderror
            </div>

            <p class="text-xs text-text-secondary">Mengubah nama universitas berlaku untuk seluruh fakultas/departemen/prodi di bawahnya.</p>

            <div class="flex items-center gap-3 pt-2">
                <button class="px-4 py-2 rounded-xl bg-brand hover:bg-brand-hover text-[#0b1420] text-sm font-semibold">Simpan</button>
                <a href="{{ route('admin.system.directory') }}" class="px-4 py-2 rounded-xl bg-bg-hover hover:bg-border text-text-primary text-sm">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
