@extends('layouts.app')

@section('title', 'Afiliasi Institusi')

@section('content')
<div class="max-w-2xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading font-bold text-2xl text-text-primary">Afiliasi Institusi</h1>
            <p class="text-sm text-text-secondary mt-0.5">Kelola perguruan tinggi tempat Anda aktif (dosen)</p>
        </div>
        <a href="{{ route('profile.index') }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">← Profil</a>
    </div>

    {{-- Daftar afiliasi --}}
    <div class="card p-6 space-y-4">
        <h2 class="font-semibold text-lg">Afiliasi Aktif</h2>

        @if ($affiliations->isEmpty())
            <p class="text-sm text-text-secondary">Belum ada afiliasi. Tambahkan perguruan tinggi di bawah.</p>
        @else
            @foreach ($affiliations as $aff)
                <div class="rounded-xl border border-border bg-bg-surface p-4 space-y-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="font-medium">{{ $aff['university']->name }}</span>
                            @if ($aff['is_primary'])
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-brand/10 text-brand">Utama</span>
                            @endif
                        </div>
                        @if ($aff['status'] === 'active')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-status-success/10 text-status-success">Aktif</span>
                        @elseif ($aff['status'] === 'pending')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-status-pending/10 text-status-pending">Menunggu Persetujuan Admin</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-status-danger/10 text-status-danger">Dicabut</span>
                        @endif
                    </div>

                    @if ($aff['faculty'] || $aff['department'] || $aff['study_program'])
                        <p class="text-xs text-text-secondary">
                            {{ $aff['faculty']?->name }} › {{ $aff['department']?->name }} › {{ $aff['study_program']?->name }}
                        </p>
                    @endif

                    @if ($aff['status'] === 'pending')
                        <p class="text-xs text-text-secondary">Menunggu persetujuan admin institusi. Akses Workspace Institusi aktif setelah disetujui.</p>
                    @endif

                    @if ($aff['status'] !== 'pending')
                        <form method="POST" action="{{ route('profile.affiliation.revoke', $aff['university']) }}" class="pt-1"
                            onsubmit="return confirm('Cabut afiliasi ini? Akses Workspace Institusi terkait akan dihapus.');">
                            @csrf
                            <button type="submit" class="text-status-danger text-sm font-medium hover:underline">Cabut Afiliasi</button>
                        </form>
                    @endif
                </div>
            @endforeach
        @endif
    </div>

    {{-- Form tambah/ubah --}}
    <div class="card p-6">
        <h2 class="font-semibold text-lg mb-1">Tambah / Ubah Afiliasi</h2>
        <p class="text-xs text-text-secondary mb-4">
            Jika institusi (prodi) sudah berlangganan, afiliasi Anda perlu disetujui admin level
            terendah sebelum Workspace Institusi dapat diakses. Institusi yang belum berlangganan langsung aktif.
        </p>

        <form method="POST" action="{{ route('profile.affiliation.update') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs text-text-secondary mb-1">Perguruan Tinggi <span class="text-status-danger">*</span></label>
                <input type="text" name="university_name" value="{{ old('university_name') }}" required
                    class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm"
                    placeholder="Universitas Indonesia">
                @error('university_name') <p class="text-status-danger text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs text-text-secondary mb-1">Fakultas</label>
                    <input type="text" name="faculty_name" value="{{ old('faculty_name') }}"
                        class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm"
                        placeholder="Fakultas Teknik">
                </div>
                <div>
                    <label class="block text-xs text-text-secondary mb-1">Departemen</label>
                    <input type="text" name="department_name" value="{{ old('department_name') }}"
                        class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm"
                        placeholder="Departemen Teknik Informatika">
                </div>
                <div>
                    <label class="block text-xs text-text-secondary mb-1">Program Studi</label>
                    <input type="text" name="study_program_name" value="{{ old('study_program_name') }}"
                        class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm"
                        placeholder="S1 Teknik Informatika">
                </div>
            </div>

            <button class="px-4 py-2 rounded-xl bg-brand-fill hover:bg-brand-fill-hover text-white text-sm font-semibold">Simpan Afiliasi</button>
        </form>
    </div>
@endsection
