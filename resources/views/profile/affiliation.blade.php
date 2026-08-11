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
            Semua tingkat (perguruan tinggi → fakultas → departemen → program studi) wajib diisi.
        </p>

        <form method="POST" action="{{ route('profile.affiliation.update') }}" class="space-y-4" id="affiliation-form">
            @csrf

            <div>
                <label class="block text-xs text-text-secondary mb-1">Perguruan Tinggi <span class="text-status-danger">*</span></label>
                <input type="text" name="university_name" id="university_name" list="dl-universities" autocomplete="off"
                    value="{{ old('university_name', $prefill['university_name'] ?? '') }}" required
                    class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm"
                    placeholder="Universitas Indonesia">
                <datalist id="dl-universities"></datalist>
                @error('university_name') <p class="text-status-danger text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs text-text-secondary mb-1">Fakultas <span class="text-status-danger">*</span></label>
                    <input type="text" name="faculty_name" id="faculty_name" list="dl-faculties" autocomplete="off"
                        value="{{ old('faculty_name', $prefill['faculty_name'] ?? '') }}" required
                        class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm"
                        placeholder="Fakultas Teknik">
                    <datalist id="dl-faculties"></datalist>
                </div>
                <div>
                    <label class="block text-xs text-text-secondary mb-1">Departemen <span class="text-status-danger">*</span></label>
                    <input type="text" name="department_name" id="department_name" list="dl-departments" autocomplete="off"
                        value="{{ old('department_name', $prefill['department_name'] ?? '') }}" required
                        class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm"
                        placeholder="Departemen Teknik Informatika">
                    <datalist id="dl-departments"></datalist>
                </div>
                <div>
                    <label class="block text-xs text-text-secondary mb-1">Program Studi <span class="text-status-danger">*</span></label>
                    <input type="text" name="study_program_name" id="study_program_name" list="dl-prodis" autocomplete="off"
                        value="{{ old('study_program_name', $prefill['study_program_name'] ?? '') }}" required
                        class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm"
                        placeholder="S1 Teknik Informatika">
                    <datalist id="dl-prodis"></datalist>
                </div>
            </div>

            <button class="px-4 py-2 rounded-xl bg-brand hover:bg-brand-hover text-[#0b1420] text-sm font-semibold">Simpan Afiliasi</button>
        </form>
    </div>
@endsection
@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var tree = @json($autocompleteTree);

        var elU = document.getElementById('university_name');
        var elF = document.getElementById('faculty_name');
        var elD = document.getElementById('department_name');
        var elP = document.getElementById('study_program_name');
        if (!elU || !elF || !elD || !elP) return;

        function setOptions(datalistId, names) {
            var dl = document.getElementById(datalistId);
            if (!dl) return;
            dl.innerHTML = '';
            names.forEach(function (name) {
                var opt = document.createElement('option');
                opt.value = name;
                dl.appendChild(opt);
            });
        }

        function norm(s) { return String(s || '').trim().toLowerCase(); }

        function findUniv() { return tree.find(function (u) { return norm(u.name) === norm(elU.value); }); }
        function findFac() { var u = findUniv(); return u ? u.faculties.find(function (f) { return norm(f.name) === norm(elF.value); }) : null; }
        function findDept() { var f = findFac(); return f ? f.departments.find(function (d) { return norm(d.name) === norm(elD.value); }) : null; }

        var allFaculties = [];
        var allDepartments = [];
        var allProdis = [];
        tree.forEach(function (u) {
            u.faculties.forEach(function (f) {
                allFaculties.push(f.name);
                f.departments.forEach(function (d) {
                    allDepartments.push(d.name);
                    d.prodis.forEach(function (p) { allProdis.push(p); });
                });
            });
        });

        function refresh() {
            var u = findUniv();
            setOptions('dl-universities', tree.map(function (x) { return x.name; }));

            var uFacs = u ? u.faculties.map(function (f) { return f.name; }) : [];
            setOptions('dl-faculties', uFacs.length ? uFacs : allFaculties);

            var f = u ? findFac() : null;
            var fDepts = f ? f.departments.map(function (d) { return d.name; }) : [];
            setOptions('dl-departments', (u && fDepts.length) ? fDepts : allDepartments);

            var d = f ? findDept() : null;
            var dProdis = d ? d.prodis : [];
            setOptions('dl-prodis', (f && dProdis.length) ? dProdis : allProdis);
        }

        elU.addEventListener('input', refresh);
        elF.addEventListener('input', refresh);
        elD.addEventListener('input', refresh);

        refresh();
    });
</script>
@endsection
