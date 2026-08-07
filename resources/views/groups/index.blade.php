@extends('layouts.app')

@section('title', 'Grup Dosen')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading font-bold text-2xl text-text-primary">Grup Dosen</h1>
            <p class="text-sm text-text-secondary mt-0.5">Kolaborasi & cross-link antar dosen di universitas yang sama</p>
        </div>
        <a href="{{ route('dashboard') }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">← Dashboard</a>
    </div>

    {{-- Info fitur (bisa dibuka/ditutup) --}}
    <div class="card p-6">
        <button type="button" data-group-info-toggle
            class="w-full flex items-center justify-between gap-3 text-left cursor-pointer group">
            <span class="flex items-center gap-2 font-heading font-semibold text-text-primary">
                <span class="material-symbols-outlined icon-md text-brand">info</span>
                Tentang Fitur Grup Dosen
            </span>
            <span data-group-info-icon class="material-symbols-outlined text-text-secondary">expand_more</span>
        </button>
        <div data-group-info-body class="hidden mt-4">
            <div class="grid sm:grid-cols-2 gap-4 text-sm">
                <div class="rounded-xl border border-border bg-bg-panel p-4">
                    <h3 class="font-semibold text-text-primary mb-2 flex items-center gap-1.5">
                        <span class="material-symbols-outlined icon-sm text-status-success">check_circle</span> Yang bisa dilakukan
                    </h3>
                    <ul class="space-y-1.5 text-text-secondary list-disc pl-4">
                        <li>Membuat grup dosen di universitas yang sama (level universitas / fakultas / departemen / prodi).</li>
                        <li>Mengundang dosen lain dan menyetujui kebersamaan agar data bisa saling terhubung (cross-link).</li>
                        <li>Dosen dalam grup yang sama (atau TA bersama) dapat melihat data bimbingan rekan dengan hubungan langsung.</li>
                    </ul>
                </div>
                <div class="rounded-xl border border-border bg-bg-panel p-4">
                    <h3 class="font-semibold text-text-primary mb-2 flex items-center gap-1.5">
                        <span class="material-symbols-outlined icon-sm text-status-danger">cancel</span> Yang tidak bisa
                    </h3>
                    <ul class="space-y-1.5 text-text-secondary list-disc pl-4">
                        <li>Grup hanya untuk dosen — mahasiswa tidak dapat menjadi anggota.</li>
                        <li>Tidak bisa dibuat lintas universitas (harus di perguruan tinggi yang sama dengan Anda).</li>
                        <li>Data bimbingan rekan hanya tampil bila ada hubungan langsung (bimbingan/penguji bersama).</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    {{-- Undangan pending --}}
    @if ($pendingInvites->isNotEmpty())
        <div class="card p-6">
            <h2 class="font-heading font-semibold text-text-primary mb-4">Undangan Menunggu ({{ $pendingInvites->count() }})</h2>
            <div class="space-y-3">
                @foreach ($pendingInvites as $group)
                    <div class="flex flex-wrap items-center justify-between gap-3 p-4 rounded-xl bg-bg-panel border border-border">
                        <div>
                            <p class="font-medium text-text-primary">{{ $group->name }}</p>
                            <p class="text-xs text-text-secondary">Dari: {{ $group->creator?->name }} · {{ $group->university?->name }}</p>
                        </div>
                        <div class="flex gap-2">
                            <form method="POST" action="{{ route('groups.approve', $group) }}">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 rounded-xl bg-brand text-white text-xs font-medium hover:opacity-90">Terima</button>
                            </form>
                            <form method="POST" action="{{ route('groups.reject', $group) }}">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 rounded-xl bg-status-danger/10 text-status-danger text-xs font-medium hover:bg-status-danger/20">Tolak</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Buat grup baru --}}
    <div class="card p-6">
        <h2 class="font-heading font-semibold text-text-primary mb-4">Buat Grup Baru</h2>
        @if (!$university)
            <p class="text-sm text-text-secondary">Lengkapi profil universitas Anda terlebih dahulu untuk membuat grup.</p>
        @else
            <form method="POST" action="{{ route('groups.store') }}" class="grid sm:grid-cols-2 gap-3">
                @csrf
                <input type="hidden" name="university_id" value="{{ $university->id }}">
                <div class="sm:col-span-2">
                    <label class="block text-xs text-text-secondary mb-1">Nama Grup</label>
                    <input type="text" name="name" required placeholder="Contoh: Dosen Teknik Informatika Universitas X"
                        class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                </div>
                <div>
                    <label class="block text-xs text-text-secondary mb-1">Level</label>
                    <select name="level" id="group-level" class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                        <option value="universitas">Universitas</option>
                        <option value="fakultas">Fakultas</option>
                        <option value="departemen">Departemen</option>
                        <option value="prodi">Program Studi</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full px-4 py-2 rounded-xl bg-brand text-white text-sm font-medium hover:opacity-90">Buat Grup</button>
                </div>

                {{-- Dropdown fakultas (level = fakultas) --}}
                <div id="group-faculty-wrap" class="hidden sm:col-span-2">
                    <label class="block text-xs text-text-secondary mb-1">Fakultas</label>
                    <select name="faculty_id" id="group-faculty" class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                        <option value="">— Pilih fakultas —</option>
                        @foreach ($faculties as $faculty)
                            <option value="{{ $faculty->id }}">{{ $faculty->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Dropdown departemen (level = departemen) --}}
                <div id="group-department-wrap" class="hidden sm:col-span-2">
                    <label class="block text-xs text-text-secondary mb-1">Departemen</label>
                    <select name="department_id" id="group-department" class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                        <option value="">— Pilih departemen —</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Dropdown prodi (level = prodi) --}}
                <div id="group-prodi-wrap" class="hidden sm:col-span-2">
                    <label class="block text-xs text-text-secondary mb-1">Program Studi</label>
                    <select name="study_program_id" id="group-prodi" class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                        <option value="">— Pilih program studi —</option>
                        @foreach ($studyPrograms as $program)
                            <option value="{{ $program->id }}">{{ $program->name }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        @endif
    </div>

    {{-- Grup yang saya ikuti --}}
    <div class="card p-6">
        <h2 class="font-heading font-semibold text-text-primary mb-4">Grup Saya ({{ $myGroups->count() }})</h2>
        @if ($myGroups->isEmpty())
            <p class="text-sm text-text-secondary">Belum bergabung dengan grup mana pun.</p>
        @else
            <div class="space-y-4">
                @foreach ($myGroups as $group)
                    <div class="p-4 rounded-xl bg-bg-panel border border-border">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                            <div>
                                <p class="font-medium text-text-primary">{{ $group->name }}</p>
                                <p class="text-xs text-text-secondary">{{ ucfirst($group->level) }} · {{ $group->university?->name }}</p>
                            </div>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-status-success/10 text-status-success">
                                <span class="w-1.5 h-1.5 rounded-full bg-status-success"></span> {{ $group->members->count() }} anggota
                            </span>
                        </div>

                        {{-- Anggota --}}
                        <div class="flex flex-wrap gap-1.5 mb-3">
                            @foreach ($group->members as $member)
                                <span class="inline-block px-2 py-0.5 rounded-full text-xs bg-bg-surface border border-border">{{ $member->name }}</span>
                            @endforeach
                        </div>

                        {{-- Undang dosen --}}
                        @if ($colleagues->isNotEmpty())
                            <form method="POST" action="{{ route('groups.invite', $group) }}" class="flex flex-col sm:flex-row gap-2">
                                @csrf
                                <select name="user_id" class="flex-1 rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                                    <option value="">Pilih dosen untuk diundang…</option>
                                    @foreach ($colleagues as $colleague)
                                        <option value="{{ $colleague->id }}">{{ $colleague->name }} ({{ $colleague->nidn ?: '—' }})</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">Undang</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Grup tersedia di universitas --}}
    @if ($availableGroups->isNotEmpty())
        <div class="card p-6">
            <h2 class="font-heading font-semibold text-text-primary mb-4">Grup Tersedia di {{ $university?->name }}</h2>
            <div class="space-y-3">
                @foreach ($availableGroups as $group)
                    <div class="flex flex-wrap items-center justify-between gap-3 p-4 rounded-xl bg-bg-panel border border-border">
                        <div>
                            <p class="font-medium text-text-primary">{{ $group->name }}</p>
                            <p class="text-xs text-text-secondary">{{ ucfirst($group->level) }} · {{ $group->members->count() }} anggota</p>
                        </div>
                        <form method="POST" action="{{ route('groups.join', $group) }}">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 rounded-xl bg-brand text-white text-xs font-medium hover:opacity-90">Gabung</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
    // Toggle dropdown fakultas/departemen/prodi sesuai level grup.
    (function () {
        var levelSelect = document.getElementById('group-level');
        if (!levelSelect) return;

        var facultyWrap = document.getElementById('group-faculty-wrap');
        var departmentWrap = document.getElementById('group-department-wrap');
        var prodiWrap = document.getElementById('group-prodi-wrap');

        function sync() {
            var level = levelSelect.value;
            facultyWrap.classList.toggle('hidden', level !== 'fakultas');
            departmentWrap.classList.toggle('hidden', level !== 'departemen');
            prodiWrap.classList.toggle('hidden', level !== 'prodi');
        }

        levelSelect.addEventListener('change', sync);
        sync();
    })();
    // Toggle card info fitur grup.
    (function () {
        var btn = document.querySelector('[data-group-info-toggle]');
        if (!btn) return;
        var body = document.querySelector('[data-group-info-body]');
        var icon = document.querySelector('[data-group-info-icon]');
        btn.addEventListener('click', function () {
            var open = !body.classList.contains('hidden');
            body.classList.toggle('hidden', open);
            if (icon) icon.textContent = open ? 'expand_more' : 'expand_less';
        });
    })();
</script>
@endsection
