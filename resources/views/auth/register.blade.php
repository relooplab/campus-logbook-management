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
    <div class="flex rounded-lg border border-border overflow-hidden mb-4 text-sm">
        <button type="button" data-role="mahasiswa" id="role-mahasiswa"
            class="role-toggle flex-1 py-2.5 font-semibold bg-brand-fill text-white transition-colors">Mahasiswa</button>
        <button type="button" data-role="dosen" id="role-dosen"
            class="role-toggle flex-1 py-2.5 font-medium bg-bg-panel text-text-secondary hover:bg-bg-hover transition-colors">Dosen</button>
    </div>
    <p id="role-hint" class="text-xs text-text-secondary mb-4 -mt-2">Daftar sebagai mahasiswa. Akun Anda perlu disetujui dosen sebelum dapat masuk.</p>

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

        {{-- ===== Opsi khusus mahasiswa (penguji) ===== --}}
        <div id="examiner-section"> <label class="flex items-center gap-2 text-sm cursor-pointer"> <input
                    type="checkbox" name="as_examiner" id="as_examiner" value="1" class="rounded bg-bg-surface"
                    {{ old("as_examiner") ? "checked" : "" }}> Saya sebagai penguji </label>
            <div id="supervisor-fields" class="hidden space-y-2 border-t border-border pt-3">
                <p class="text-xs text-text-secondary">Nama pembimbing mahasiswa yang akan Anda uji (maks 3):</p> <input
                    type="text" name="supervisor_1" placeholder="Pembimbing 1" value="{{ old("supervisor_1") }}"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> <input type="text"
                    name="supervisor_2" placeholder="Pembimbing 2 (opsional)" value="{{ old("supervisor_2") }}"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> <input type="text"
                    name="supervisor_3" placeholder="Pembimbing 3 (opsional)" value="{{ old("supervisor_3") }}"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
            </div>
        </div>

        <button type="submit"
            class="w-full rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white py-2 text-sm font-semibold">Daftar</button>
        <a href="{{ route("login") }}" class="block text-center text-sm text-brand hover:underline">Sudah punya akun?
            Masuk</a>
    </form>
    @endsection @section("guest-scripts")
    <script>
        var roleInput = document.getElementById('role-input');
        var examinerSection = document.getElementById('examiner-section');
        var roleHint = document.getElementById('role-hint');
        var toggles = document.querySelectorAll('.role-toggle');

        function setRole(role) {
            roleInput.value = role;
            toggles.forEach(function (btn) {
                var active = btn.dataset.role === role;
                btn.classList.toggle('bg-brand-fill', active);
                btn.classList.toggle('text-white', active);
                btn.classList.toggle('font-semibold', active);
                btn.classList.toggle('bg-bg-panel', !active);
                btn.classList.toggle('text-text-secondary', !active);
                btn.classList.toggle('font-medium', !active);
            });
            // Opsi "penguji" hanya untuk mahasiswa; sembunyikan untuk dosen.
            examinerSection.classList.toggle('hidden', role === 'dosen');
            roleHint.textContent = role === 'dosen'
                ? 'Daftar sebagai dosen. Akun Anda perlu disetujui admin sebelum dapat masuk.'
                : 'Daftar sebagai mahasiswa. Akun Anda perlu disetujui dosen sebelum dapat masuk.';
        }

        toggles.forEach(function (btn) {
            btn.addEventListener('click', function () { setRole(btn.dataset.role); });
        });

        // Toggle isi field supervisor (checkbox "penguji").
        var chk = document.getElementById('as_examiner');
        var fields = document.getElementById('supervisor-fields');
        function toggleExaminer() {
            fields.classList.toggle('hidden', !chk.checked);
        }
        chk.addEventListener('change', toggleExaminer);
        toggleExaminer();

        // Pertahankan role yang dipilih saat ada error validasi (old input).
        var oldRole = {{ json_encode(old('role', 'mahasiswa')) }};
        setRole(oldRole === 'dosen' ? 'dosen' : 'mahasiswa');
    </script>
@endsection