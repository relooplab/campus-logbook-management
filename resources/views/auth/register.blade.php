@extends("layouts.guest")
@section("title", "Daftar")
@section("guest-content")
    <h2 class="text-lg font-semibold mb-4">Daftar Mahasiswa</h2>
    @if ($errors->any())
        <div class="mb-4 px-3 py-2 rounded-md bg-status-danger/10 text-status-danger text-sm">
            @foreach ($errors->all() as $e)
                <p>{{ $e }}</p>
            @endforeach
        </div>
    @endif
    <form method="POST" action="{{ route("register") }}" class="space-y-4"> @csrf <div> <label
                class="block text-sm font-medium mb-1" for="name">Nama</label> <input type="text" name="name"
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
                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div> <label
            class="flex items-center gap-2 text-sm cursor-pointer"> <input type="checkbox" name="as_examiner"
                id="as_examiner" value="1" class="rounded bg-bg-surface" {{ old("as_examiner") ? "checked" : "" }}>
            Saya juga penguji — mencatat sidang mahasiswa lain </label>
        <div id="supervisor-fields" class="hidden space-y-2 border-t border-border pt-3">
            <p class="text-xs text-text-secondary">Nama pembimbing mahasiswa yang akan Anda uji (maks 3):</p> <input
                type="text" name="supervisor_1" placeholder="Pembimbing 1" value="{{ old("supervisor_1") }}"
                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> <input type="text"
                name="supervisor_2" placeholder="Pembimbing 2 (opsional)" value="{{ old("supervisor_2") }}"
                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> <input type="text"
                name="supervisor_3" placeholder="Pembimbing 3 (opsional)" value="{{ old("supervisor_3") }}"
                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
        </div> <button type="submit"
            class="w-full rounded-md bg-accent-teal hover:bg-accent-teal/90 text-white py-2 text-sm font-semibold">Daftar</button>
        <a href="{{ route("login") }}" class="block text-center text-sm text-accent-teal hover:underline">Sudah punya akun?
            Masuk</a>
    </form>
    @endsection @section("guest-scripts")
    <script>
        var chk = document.getElementById('as_examiner');
        var fields = document.getElementById('supervisor-fields');

        function toggle() {
            fields.classList.toggle('hidden', !chk.checked);
        }
        chk.addEventListener('change', toggle);
        toggle();
    </script>
@endsection
