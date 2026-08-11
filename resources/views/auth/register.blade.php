@extends("layouts.guest")
@section("title", "Daftar")
@section("guest-content")
    <h2 class="text-lg font-semibold mb-4 text-text-primary">Daftar Akun</h2>
    @if ($errors->any())
        <div class="mb-4 px-3 py-2 rounded-xl bg-status-danger/10 text-status-danger text-sm">
            @foreach ($errors->all() as $e)
                <p>{{ $e }}</p>
            @endforeach
        </div>
    @endif

    {{-- ===== Pemilih peran (Mahasiswa / Dosen) ===== --}}
    <div id="role-tabs" class="flex rounded-xl border border-border overflow-hidden mb-4 text-sm" role="tablist" aria-label="Pilih peran pendaftaran">
        <button type="button" data-role="mahasiswa" id="role-mahasiswa" role="tab" aria-selected="true" aria-controls="role-panels"
            class="role-toggle flex-1 py-2.5 font-semibold bg-brand text-[#0b1420] transition-colors cursor-pointer">Mahasiswa</button>
        <button type="button" data-role="dosen" id="role-dosen" role="tab" aria-selected="false" aria-controls="role-panels"
            class="role-toggle flex-1 py-2.5 font-medium bg-bg-panel text-text-secondary hover:bg-bg-hover transition-colors cursor-pointer">Dosen</button>
    </div>
    <p id="role-hint" class="text-xs text-text-secondary mb-4 -mt-2">Daftar sebagai mahasiswa. Anda dapat langsung memilih dosen pembimbing.</p>

    <form method="POST" action="{{ route("register") }}" class="space-y-4" id="role-panels"> @csrf <input type="hidden" name="role"
            id="role-input" value="mahasiswa">
        {{-- ===== Opsi khusus mahasiswa (NIM) — data instansi diisi di halaman profil ===== --}}
        <div id="mahasiswa-nim-section" class="hidden space-y-2">
            <div>
                <label class="block text-sm font-medium mb-1" for="nim">NIM</label>
                <input type="text" name="nim" id="nim" value="{{ old("nim") }}" placeholder="Nomor Induk Mahasiswa" autocomplete="off"
                    class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-brand/40 focus:outline-none">
                @error('nim')
                    <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- ===== Opsi khusus dosen (NIDN) — data instansi diisi di halaman profil ===== --}}
        <div id="dosen-directory-section" class="hidden space-y-2">
            <div>
                <label class="block text-sm font-medium mb-1" for="nidn">NIDN</label>
                <input type="text" name="nidn" id="nidn" value="{{ old("nidn") }}" placeholder="Nomor Induk Dosen Nasional"
                    class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-brand/40 focus:outline-none">
                @error('nidn')
                    <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div> <label class="block text-sm font-medium mb-1" for="name">Nama</label> <input type="text" name="name"
                id="name" required value="{{ old("name") }}" autocomplete="name"
                class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-brand/40 focus:outline-none"> </div>
        <div> <label class="block text-sm font-medium mb-1" for="email">Email</label> <input type="email"
                name="email" id="email" required value="{{ old("email") }}" autocomplete="email"
                class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-brand/40 focus:outline-none"> </div>
        <div> <label class="block text-sm font-medium mb-1" for="password">Kata Sandi</label> <input type="password"
                name="password" id="password" required minlength="6" autocomplete="new-password"
                class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-brand/40 focus:outline-none"> </div>
        <div> <label class="block text-sm font-medium mb-1" for="password_confirmation">Konfirmasi Kata Sandi</label> <input
                type="password" name="password_confirmation" id="password_confirmation" required minlength="6" autocomplete="new-password"
                class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-brand/40 focus:outline-none"> </div>

        <button type="submit"
            class="w-full rounded-xl bg-brand hover:bg-brand-hover text-[#0b1420] py-2 text-sm font-semibold transition-colors">Daftar</button>
        <a href="{{ route("login") }}" class="block text-center text-sm text-brand hover:underline">Sudah punya akun?
            Masuk</a>
    </form>
    @endsection
    @section("guest-scripts")
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var roleInput = document.getElementById('role-input');
            var dosenDirectorySection = document.getElementById('dosen-directory-section');
            var mahasiswaNimSection = document.getElementById('mahasiswa-nim-section');
            var roleHint = document.getElementById('role-hint');
            var roleTabs = document.getElementById('role-tabs');

            if (!roleInput || !roleTabs) return;

            function setRole(role) {
                roleInput.value = role;
                var toggles = roleTabs.querySelectorAll('.role-toggle');
                Array.prototype.forEach.call(toggles, function (btn) {
                    var active = btn.dataset.role === role;
                    btn.classList.toggle('bg-brand', active);
                    btn.classList.toggle('text-[#0b1420]', active);
                    btn.classList.toggle('font-semibold', active);
                    btn.classList.toggle('bg-bg-panel', !active);
                    btn.classList.toggle('text-text-secondary', !active);
                    btn.classList.toggle('font-medium', !active);
                    btn.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                // Direktori organisasi & NIDN hanya untuk dosen; NIM hanya untuk mahasiswa.
                if (dosenDirectorySection) dosenDirectorySection.classList.toggle('hidden', role !== 'dosen');
                if (mahasiswaNimSection) mahasiswaNimSection.classList.toggle('hidden', role !== 'mahasiswa');
                if (roleHint) {
                    roleHint.textContent = role === 'dosen'
                        ? 'Daftar sebagai dosen. Setelah mendaftar, lengkapi data institusi Anda di halaman profil.'
                        : 'Daftar sebagai mahasiswa. Setelah verifikasi email, Anda dapat memilih dosen pembimbing.';
                }
            }

            // Event delegation: lebih robust, listener tetap aktif meski elemen berubah.
            roleTabs.addEventListener('click', function (e) {
                var btn = e.target.closest('.role-toggle');
                if (btn) setRole(btn.dataset.role);
            });

            // Pertahankan role yang dipilih saat ada error validasi (old input).
            // Gunakan sintaks Json (bukan kurung kurawal) agar nilai tidak di-escape dari tanda kutip di dalam script.
            var oldRole = @json(old('role', 'mahasiswa'));
            setRole(oldRole === 'dosen' ? 'dosen' : 'mahasiswa');
        });
    </script>
    @endsection
