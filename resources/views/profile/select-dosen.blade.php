@extends('layouts.app')

@section('title', 'Pilih Dosen')

@section('content')
<div class="max-w-2xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading font-bold text-2xl text-text-primary">Pilih Dosen</h1>
            <p class="text-sm text-text-secondary mt-0.5">Pilih dosen pembimbing dan penguji untuk program Anda</p>
        </div>
        <a href="{{ route('profile.index') }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">← Profil</a>
    </div>

    <div class="card p-6">
        <form method="POST" action="{{ route('profile.store-dosen') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs text-text-secondary mb-1">Jenis Program</label>
                <select name="jenis" required class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm">
                    <option value="ta">Tugas Akhir (TA)</option>
                    <option value="kp">Kerja Praktek (KP)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs text-text-secondary mb-1">Pembimbing 1 <span class="text-status-danger">*</span></label>
                <select name="pembimbing_1_id" required class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm">
                    <option value="">— Pilih dosen —</option>
                    @foreach ($dosenList as $dosen)
                        <option value="{{ $dosen->id }}">{{ $dosen->name }} ({{ $dosen->nidn ?: '—' }})</option>
                    @endforeach
                </select>
                @error('pembimbing_1_id') <p class="text-status-danger text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs text-text-secondary mb-1">Pembimbing 2 <span class="text-text-secondary">(opsional)</span></label>
                <select name="pembimbing_2_id" class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm">
                    <option value="">— Pilih dosen —</option>
                    @foreach ($dosenList as $dosen)
                        <option value="{{ $dosen->id }}">{{ $dosen->name }} ({{ $dosen->nidn ?: '—' }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs text-text-secondary mb-1">Penguji 1 <span class="text-text-secondary">(opsional)</span></label>
                <select name="penguji_1_id" class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm">
                    <option value="">— Pilih dosen —</option>
                    @foreach ($dosenList as $dosen)
                        <option value="{{ $dosen->id }}">{{ $dosen->name }} ({{ $dosen->nidn ?: '—' }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs text-text-secondary mb-1">Penguji 2 <span class="text-text-secondary">(opsional)</span></label>
                <select name="penguji_2_id" class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm">
                    <option value="">— Pilih dosen —</option>
                    @foreach ($dosenList as $dosen)
                        <option value="{{ $dosen->id }}">{{ $dosen->name }} ({{ $dosen->nidn ?: '—' }})</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-wrap gap-2 pt-2">
                <button type="submit" class="px-4 py-2 rounded-xl bg-brand text-white text-sm font-medium hover:opacity-90">Kirim Permintaan</button>
                <a href="{{ route('profile.index') }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection