@extends("layouts.guest")
@section("title", "Daftar")
@section("guest-content")
    <h2 class="text-lg font-semibold mb-4">Daftar Akun</h2>
    @if ($errors->any())
        <div class="mb-4 px-3 py-2 rounded-md bg-status-danger/10 text-status-danger text-sm">
            @foreach ($errors->all() as $e)
                <p>{{ $e }}</p>
            @endforeach
        </div>
    @endif

    {{-- ===== Pemilih peran (Mahasiswa / Dosen) ===== --}}
    <div id="role-tabs" class="flex rounded-lg border border-border overflow-hidden mb-4 text-sm">
        <button type="button" data-role="mahasiswa" id="role-mahasiswa"
            class="role-toggle flex-1 py-2.5 font-semibold bg-brand-fill text-white transition-colors cursor-pointer">Mahasiswa</button>
        <button type="button" data-role="dosen" id="role-dosen"
            class="role-toggle flex-1 py-2.5 font-medium bg-bg-panel text-text-secondary hover:bg-bg-hover transition-colors cursor-pointer">Dosen</button>
    </div>
    <p id="role-hint" class="text-xs text-text-secondary mb-4 -mt-2">Daftar sebagai mahasiswa. Anda dapat langsung memilih dosen pembimbing.</p>

    <form method="POST" action="{{ route("register") }}" class="space-y-4"> @csrf <input type="hidden" name="role"
            id="role-input" value="mahasiswa">
        <div> <label class="block text-sm font-medium mb-1" for="name">Nama</label> <input type="text" name="name"
                id="name" required value="{{ old("name") }}"
                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
        <div> <label class="block text-sm font-medium mb-1" for="email">Email</label> <input type="email"
                name="email" id="email" required value="{{ old("email") }}"
                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
        <div> <label class="block text-sm font-medium mb-1" for="password">Kata Sandi</label> <input type="password"
                name="password" id="password" required minlength="6"
                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
        <div> <label class="block text-sm font-medium mb-1" for="password_confirmation">Konfirmasi Kata Sandi</label> <input
                type="password" name="password_confirmation" id="password_confirmation" required minlength="6"
                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>

        {{-- ===== Opsi khusus dosen (NIDN + direktori organisasi) ===== --}}
        <div id="dosen-directory-section" class="hidden space-y-2 border-t border-border pt-3">
            <p class="text-sm font-medium text-text-primary">Direktori Organisasi Anda</p>
            <p class="text-xs text-text-secondary">Nama perguruan tinggi yang sudah ada akan dipakai otomatis (tidak duplikat).</p>
            <div>
                <label class="block text-sm font-medium mb-1" for="nidn">NIDN</label>
                <input type="text" name="nidn" id="nidn" value="{{ old("nidn") }}" placeholder="Nomor Induk Dosen Nasional"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                @error('nidn')
                    <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="university_name">Perguruan Tinggi</label>
                <input type="text" name="university_name" id="university_name" value="{{ old("university_name") }}"
                    placeholder="Nama universitas / institut / politeknik"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                @error('university_name')
                    <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="university_npsn">NPSN (opsional)</label>
                <input type="text" name="university_npsn" id="university_npsn" value="{{ old("university_npsn") }}"
                    placeholder="Nomor Pokok Sekolah Nasional"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="faculty_name">Fakultas</label>
                <input type="text" name="faculty_name" id="faculty_name" value="{{ old("faculty_name") }}"
                    placeholder="Contoh: Fakultas Teknik"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="department_name">Departemen</label>
                <input type="text" name="department_name" id="department_name" value="{{ old("department_name") }}"
                    placeholder="Contoh: Departemen Teknik Informatika"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="study_program_name">Program Studi</label>
                <input type="text" name="study_program_name" id="study_program_name" value="{{ old("study_program_name") }}"
                    placeholder="Contoh: S1 Teknik Informatika"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="study_program_code">Kode Prodi (opsional)</label>
                <input type="text" name="study_program_code" id="study_program_code" value="{{ old("study_program_code") }}"
                    placeholder="Contoh: 55201"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
            </div>
        </div>

        <button type="submit"
            class="w-full rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white py-2 text-sm font-semibold">Daftar</button>
        <a href="{{ route("login") }}" class="block text-center text-sm text-brand hover:underline">Sudah punya akun?
            Masuk</a>
    </form>
    @endsection
    @section("guest-scripts")
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var roleInput = document.getElementById('role-input');
            var dosenDirectorySection = document.getElementById('dosen-directory-section');
            var roleHint = document.getElementById('role-hint');
            var roleTabs = document.getElementById('role-tabs');

            if (!roleInput || !roleTabs) return;

            function setRole(role) {
                roleInput.value = role;
                var toggles = roleTabs.querySelectorAll('.role-toggle');
                Array.prototype.forEach.call(toggles, function (btn) {
                    var active = btn.dataset.role === role;
                    btn.classList.toggle('bg-brand-fill', active);
                    btn.classList.toggle('text-white', active);
                    btn.classList.toggle('font-semibold', active);
                    btn.classList.toggle('bg-bg-panel', !active);
                    btn.classList.toggle('text-text-secondary', !active);
                    btn.classList.toggle('font-medium', !active);
                });
                // Direktori organisasi hanya untuk dosen.
                if (dosenDirectorySection) dosenDirectorySection.classList.toggle('hidden', role !== 'dosen');
                if (roleHint) {
                    roleHint.textContent = role === 'dosen'
                        ? 'Daftar sebagai dosen. Akun Anda perlu disetujui admin sebelum dapat masuk.'
                        : 'Daftar sebagai mahasiswa. Setelah verifikasi email, Anda dapat memilih dosen pembimbing.';
                }
            }

            // Event delegation: lebih robust, listener tetap aktif meski elemen berubah.
            roleTabs.addEventListener('click', function (e) {
                var btn = e.target.closest('.role-toggle');
                if (btn) setRole(btn.dataset.role);
            });

            // Pertahankan role yang dipilih saat ada error validasi (old input).
            var oldRole = {{ json_encode(old('role', 'mahasiswa')) }};
            setRole(oldRole === 'dosen' ? 'dosen' : 'mahasiswa');
        });
    </script>
    @endsection
