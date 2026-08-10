@extends("layouts.app") @section("title", "Profil") @section("content")
@php
    // Direktori afiliasi (PT → fakultas → departemen → prodi) untuk cascade JS.
    $affiliationTree = $universities->map(fn ($u) => [
        'id' => $u->id,
        'name' => $u->name,
        'faculties' => $u->faculties->map(fn ($f) => [
            'id' => $f->id,
            'name' => $f->name,
            'departments' => $f->departments->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'prodis' => $d->studyPrograms->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values(),
            ])->values(),
        ])->values(),
    ])->values();
@endphp
<div class="max-w-2xl space-y-6">
    <h1 class="text-xl font-bold">Profil</h1> {{-- Data profil --}} <div
        class="bg-bg-surface rounded-xl border border-border p-6 space-y-4">
        <div class="flex items-center gap-4">
            <div
                class="h-20 w-20 rounded-full overflow-hidden bg-brand text-[#0b1420] flex items-center justify-center text-2xl font-bold flex-shrink-0">
                @if ($user->photoUrl())
                    <img src="{{ $user->photoUrl() }}" class="h-full w-full object-cover" alt="Foto profil">
                @else
                    {{ $user->initials() }}
                @endif
            </div>
            <div>
                <h2 class="font-semibold text-lg">{{ $user->name }}</h2>
                <p class="text-sm text-text-secondary">{{ $user->email }}</p>
                @foreach ($user->roles->whereNotIn('name', ['admin', 'system_admin']) as $r)
                    <span
                        class="inline-block px-2 py-0.5 rounded-full text-xs bg-bg-panel mt-1 mr-1">{{ ucfirst($r->name) }}</span>
                @endforeach
            </div>
        </div>
        <form method="POST" action="{{ route("profile.update") }}" enctype="multipart/form-data" class="space-y-4">
            @csrf @method("PUT") <div class="grid sm:grid-cols-2 gap-4">
                <div> <label class="block text-sm font-medium mb-1" for="name">Nama Lengkap</label> <input
                        type="text" name="name" id="name" required value="{{ old("name", $user->name) }}"
                        class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:outline-none">
                    @error("name")
                        <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div> <label class="block text-sm font-medium mb-1" for="identifier">NIM / NIDN @if ($user->isMahasiswa())<span class="text-status-danger">*</span>@endif</label> <input
                        type="text" name="identifier" id="identifier" @if ($user->isMahasiswa()) required @endif
                        value="{{ old("identifier", $user->identifier) }}"
                        class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    @error("identifier")
                        <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div> <label class="block text-sm font-medium mb-1">Foto Profil</label> <input type="file"
                        name="photo" accept="image/*" class="w-full text-sm"> @error("photo")
                        <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div> <label class="block text-sm font-medium mb-1" for="whatsapp">Nomor WhatsApp @if ($user->isMahasiswa())<span class="text-status-danger">*</span>@endif</label> <input
                        type="text" name="whatsapp" id="whatsapp" @if ($user->isMahasiswa()) required @endif value="{{ old("whatsapp", $user->whatsapp) }}"
                        placeholder="6281xxxxxx"
                        class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    @if ($user->isDosen())
                        <label class="mt-2 flex items-center gap-2 text-xs text-text-secondary cursor-pointer">
                            <input type="checkbox" name="bimbingan_via_whatsapp" value="1"
                                @checked(old('bimbingan_via_whatsapp', $user->bimbingan_via_whatsapp))
                                class="rounded border-border">
                            Kontak mahasiswa lewat jalur ini untuk bimbingan
                        </label>
                    @endif
                </div>
                <div> <label class="block text-sm font-medium mb-1" for="telegram">Telegram</label> <input
                        type="text" name="telegram" id="telegram" value="{{ old("telegram", $user->telegram) }}"
                        placeholder="@username"
                        class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    @if ($user->isDosen())
                        <label class="mt-2 flex items-center gap-2 text-xs text-text-secondary cursor-pointer">
                            <input type="checkbox" name="bimbingan_via_telegram" value="1"
                                @checked(old('bimbingan_via_telegram', $user->bimbingan_via_telegram))
                                class="rounded border-border">
                            Kontak mahasiswa lewat jalur ini untuk bimbingan
                        </label>
                    @endif
                </div>
                <div> <label class="block text-sm font-medium mb-1" for="linkedin">LinkedIn</label> <input
                        type="url" name="linkedin" id="linkedin" value="{{ old("linkedin", $user->linkedin) }}"
                        placeholder="https://linkedin.com/in/..."
                        class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
            </div>
            @if ($user->isDosen())
                <div class="pt-2 border-t border-border">
                    <p class="text-sm font-semibold mb-3">Tautan Akademik (Dosen)</p>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div> <label class="block text-sm font-medium mb-1" for="google_scholar">Google Scholar</label>
                            <input type="url" name="google_scholar" id="google_scholar"
                                value="{{ old("google_scholar", $user->google_scholar) }}"
                                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                        </div>
                        <div> <label class="block text-sm font-medium mb-1" for="orcid">ORCID</label> <input
                                type="text" name="orcid" id="orcid" value="{{ old("orcid", $user->orcid) }}"
                                placeholder="0000-0000-0000-0000"
                                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
                        <div> <label class="block text-sm font-medium mb-1" for="sinta">SINTA ID</label> <input
                                type="text" name="sinta" id="sinta" value="{{ old("sinta", $user->sinta) }}"
                                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
                        <div> <label class="block text-sm font-medium mb-1" for="researchgate">ResearchGate</label>
                            <input type="url" name="researchgate" id="researchgate"
                                value="{{ old("researchgate", $user->researchgate) }}"
                                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium mb-1" for="jadwal_bimbingan_url">Link Jadwalkan Bimbingan</label>
                            <input type="url" name="jadwal_bimbingan_url" id="jadwal_bimbingan_url"
                                value="{{ old("jadwal_bimbingan_url", $user->jadwal_bimbingan_url) }}"
                                placeholder="https://cal.com/... atau https://forms.gle/..."
                                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                            <p class="text-xs text-text-secondary mt-1">Link ini akan ditampilkan sebagai card di halaman Jadwalkan Bimbingan agar mahasiswa dapat memesan/bergabung sesi bimbingan Anda. Kosongkan jika belum tersedia.</p>
                        </div>
                    </div>
                </div>
            @endif
            <div class="flex items-center gap-3 pt-2"> <button
                    class="px-4 py-2 rounded-md bg-brand hover:bg-brand-hover text-[#0b1420] text-sm font-semibold">Simpan
                    Profil</button> </div>
        </form>
        @include('partials.profile-affiliation', ['affUser' => $user])
    </div>

    @if ($user->isMahasiswa())
        <div class="bg-bg-surface rounded-xl border border-border p-6" id="kartu-afiliasi">
            <h2 class="font-semibold mb-1">Afiliasi Perguruan Tinggi</h2>
            <p class="text-sm text-text-secondary mb-3">Wajib diisi sebelum memilih dosen — pilih perguruan tinggi, fakultas, departemen, dan program studi Anda dari data yang tersedia.</p>

            @if ($affiliation?->pivot?->study_program_id)
                @php
                    $affFac = $affiliation->pivot->faculty_id ? \App\Models\Faculty::find($affiliation->pivot->faculty_id) : null;
                    $affDept = $affiliation->pivot->department_id ? \App\Models\Department::find($affiliation->pivot->department_id) : null;
                    $affProdi = $affiliation->pivot->study_program_id ? \App\Models\StudyProgram::find($affiliation->pivot->study_program_id) : null;
                @endphp
                <div class="mb-3 rounded-xl bg-bg-panel border border-border p-3 text-sm">
                    <p class="font-medium text-text-primary">{{ $affiliation->name }}</p>
                    <p class="text-xs text-text-secondary mt-0.5">{{ $affFac?->name }} › {{ $affDept?->name }} › {{ $affProdi?->name }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('profile.affiliation-mahasiswa.update') }}" class="space-y-3">
                @csrf
                <div class="grid sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-text-secondary mb-1">Perguruan Tinggi <span class="text-status-danger">*</span></label>
                        <select name="university_id" id="aff-university" required class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                            <option value="">— Pilih perguruan tinggi —</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-text-secondary mb-1">Fakultas <span class="text-status-danger">*</span></label>
                        <select name="faculty_id" id="aff-faculty" required disabled class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                            <option value="">— Pilih fakultas —</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-text-secondary mb-1">Departemen <span class="text-status-danger">*</span></label>
                        <select name="department_id" id="aff-department" required disabled class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                            <option value="">— Pilih departemen —</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-text-secondary mb-1">Program Studi <span class="text-status-danger">*</span></label>
                        <select name="study_program_id" id="aff-prodi" required disabled class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                            <option value="">— Pilih prodi —</option>
                        </select>
                    </div>
                </div>
                @error('university_id')
                    <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                @enderror
                <button class="px-4 py-2 rounded-md bg-brand hover:bg-brand-hover text-[#0b1420] text-sm font-semibold">Simpan Afiliasi</button>
            </form>
        </div>
    @endif

    @if ($user->isDosen())
        <div class="bg-bg-surface rounded-xl border border-border p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-semibold">Data Institusi / Afiliasi</h2>
                    <p class="text-sm text-text-secondary mt-0.5">Perguruan tinggi, fakultas, departemen, dan program studi Anda.</p>
                </div>
                <a href="{{ route('profile.affiliation') }}"
                    class="px-4 py-2 rounded-md bg-brand hover:bg-brand-hover text-[#0b1420] text-sm font-semibold">Kelola</a>
            </div>
        </div>
    @endif

    @if ($user->isMahasiswa() && $programs->isNotEmpty())
        @foreach ($programs as $prog)
            <div class="bg-bg-surface rounded-xl border border-border p-6">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                    <h2 class="font-semibold">{{ $prog->jenisLabel() }}</h2>
                    @include('partials.status-badge', ['status' => $prog->status_ta])
                </div>
                @if ($prog->isKp())
                    <p class="text-sm mb-3"><span class="text-text-secondary">Tempat Kerja Praktek:</span> <span class="font-medium text-text-primary break-words">{{ $prog->tempat_kp ?: 'Belum diisi' }}</span></p>
                @else
                    <p class="text-sm mb-3"><span class="text-text-secondary">Judul Tugas Akhir:</span> <span class="font-medium text-text-primary break-words">{{ $prog->judul_ta ?: 'Belum diisi' }}</span></p>
                @endif
                <form method="POST" action="{{ route('profile.program', $prog) }}" class="space-y-3">
                    @csrf
                    @method('PUT')
                    @if ($prog->isKp())
                        <div>
                            <label class="block text-xs text-text-secondary mb-1">Tempat Kerja Praktek <span class="text-status-danger">*</span></label>
                            <input type="text" name="tempat_kp" required value="{{ old('tempat_kp', $prog->tempat_kp) }}" placeholder="Contoh: PT Teknologi Indonesia" class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                            @error('tempat_kp') <p class="text-status-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <div>
                            <label class="block text-xs text-text-secondary mb-1">Judul Tugas Akhir <span class="text-status-danger">*</span></label>
                            <input type="text" name="judul_ta" required value="{{ old('judul_ta', $prog->judul_ta) }}" placeholder="Contoh: Rancang Bangun Sistem ..." class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                            @error('judul_ta') <p class="text-status-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endif
                    <button class="px-4 py-2 rounded-md bg-brand hover:bg-brand-hover text-[#0b1420] text-sm font-semibold">Simpan {{ $prog->isKp() ? 'Tempat KP' : 'Judul' }}</button>
                </form>
                @if ($prog->isKp() && in_array($prog->fase, ['laporan', 'seminar_kp', 'selesai'], true)
                    && $programs->where('jenis', 'ta')->isEmpty())
                    <div class="mt-4 pt-3 border-t border-border">
                        <a href="{{ route('profile.select-dosen') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-control bg-brand text-[#0b1420] text-xs font-semibold hover:bg-brand-hover transition-colors">
                            <span class="material-symbols-outlined icon-sm">school</span> Lanjut ke Tugas Akhir
                        </a>
                    </div>
                @endif
                
            </div>
        @endforeach
    @endif

    {{-- Ganti kata sandi --}} <div class="bg-bg-surface rounded-xl border border-border p-6 space-y-4">
        <h2 class="font-semibold">Ganti Kata Sandi</h2>
        <form method="POST" action="{{ route("profile.password") }}" class="space-y-4"> @csrf @method("PUT") <div>
                <label class="block text-sm font-medium mb-1" for="current_password">Kata Sandi Saat Ini</label>
                <input type="password" name="current_password" id="current_password" required
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                @error("current_password")
                    <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <div> <label class="block text-sm font-medium mb-1" for="password">Kata Sandi Baru</label> <input
                        type="password" name="password" id="password" required minlength="6"
                        class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    @error("password")
                        <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div> <label class="block text-sm font-medium mb-1" for="password_confirmation">Konfirmasi</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" required
                        minlength="6" class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                </div>
            </div> <button class="px-4 py-2 rounded-md bg-brand hover:bg-brand-hover text-[#0b1420] text-sm">Ubah
                Kata
                Sandi</button>
        </form>
    </div>
</div>
@endsection @section('scripts')
<script>
    // Cascade afiliasi mahasiswa: PT → fakultas → departemen → prodi.
    (function () {
        var tree = @json($affiliationTree);

        var elU = document.getElementById('aff-university');
        var elF = document.getElementById('aff-faculty');
        var elD = document.getElementById('aff-department');
        var elP = document.getElementById('aff-prodi');
        if (!elU || !elF || !elD || !elP) return;

        var preselect = {
            university: @json($affiliation?->id ?? null),
            faculty: @json($affiliation?->pivot?->faculty_id ?? null),
            department: @json($affiliation?->pivot?->department_id ?? null),
            prodi: @json($affiliation?->pivot?->study_program_id ?? null)
        };

        function fill(select, options, selectedId) {
            select.innerHTML = '<option value="">— Pilih —</option>';
            options.forEach(function (o) {
                var opt = document.createElement('option');
                opt.value = o.id;
                opt.textContent = o.name;
                if (o.id === selectedId) opt.selected = true;
                select.appendChild(opt);
            });
            select.disabled = options.length === 0;
        }

        function univ() { return tree.find(function (u) { return u.id === parseInt(elU.value, 10); }); }
        function fac() { var u = univ(); return u ? u.faculties.find(function (f) { return f.id === parseInt(elF.value, 10); }) : null; }
        function dept() { var f = fac(); return f ? f.departments.find(function (d) { return d.id === parseInt(elD.value, 10); }) : null; }

        function resetDown(step) {
            if (step <= 1) { elF.innerHTML = '<option value="">— Pilih —</option>'; elF.disabled = true; }
            if (step <= 2) { elD.innerHTML = '<option value="">— Pilih —</option>'; elD.disabled = true; }
            if (step <= 3) { elP.innerHTML = '<option value="">— Pilih —</option>'; elP.disabled = true; }
        }

        function fillFac() { var u = univ(); resetDown(1); fill(elF, u ? u.faculties : [], null); }
        function fillDept() { var f = fac(); resetDown(2); fill(elD, f ? f.departments : [], null); }
        function fillProdi() { var d = dept(); resetDown(3); fill(elP, d ? d.prodis : [], null); }

        elU.addEventListener('change', fillFac);
        elF.addEventListener('change', fillDept);
        elD.addEventListener('change', fillProdi);

        // Initial: isi universitas + preselect afiliasi yang sudah ada.
        tree.forEach(function (u) {
            var opt = document.createElement('option');
            opt.value = u.id;
            opt.textContent = u.name;
            elU.appendChild(opt);
        });
        if (preselect.university) {
            elU.value = preselect.university;
            fillFac();
            if (preselect.faculty) { elF.value = preselect.faculty; fillDept(); }
            if (preselect.department) { elD.value = preselect.department; fillProdi(); }
            if (preselect.prodi) { elP.value = preselect.prodi; }
        }
    })();
</script>
@endsection
