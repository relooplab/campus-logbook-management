@extends("layouts.app") @section("title", "Kelola Admin") @section("content")
<div class="space-y-4">
    <h1 class="text-xl font-bold">Kelola Admin</h1>
    <p class="text-sm text-text-secondary">Kelola akun admin operasional. Halaman ini hanya dapat diakses oleh System Admin.</p>

    <div class="grid lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 bg-bg-surface rounded-xl border border-border overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-text-secondary border-b border-border">
                        <th class="py-3 px-4">Nama</th>
                        <th class="py-3 px-4 table-col-identifier">Identifier</th>
                        <th class="py-3 px-4 table-col-email">Email</th>
                        <th class="py-3 px-4">Role</th>
                        <th class="py-3 px-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($admins as $u)
                        <tr class="border-b border-border">
                            <td class="py-3 px-4">
                                {{ $u->name }}
                                @if ($u->id === auth()->id())
                                    <span class="inline-block px-2 py-0.5 rounded-full text-[10px] bg-brand/10 text-brand ml-1">Anda</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 table-col-identifier">{{ $u->identifier ?? "—" }}</td>
                            <td class="py-3 px-4 table-col-email">{{ $u->email }}</td>
                            <td class="py-3 px-4">
                                @foreach ($u->roles as $role)
                                    <span class="inline-block px-2 py-0.5 rounded-full text-xs bg-bg-panel mr-1">{{ $role->name }}</span>
                                @endforeach
                            </td>
                            <td class="py-3 px-4">
                                @if ($u->id !== auth()->id())
                                    <button type="button" data-reset="{{ $u->id }}"
                                        data-name="{{ $u->name }}"
                                        class="reset-btn text-brand hover:underline text-xs mr-2">Reset PW</button>
                                    <form method="POST" action="{{ route("admin.system.admins.destroy", $u) }}"
                                        onsubmit="return confirm('Hapus akun admin ini?')" class="inline"> @csrf
                                        @method("DELETE") <button
                                            class="text-status-danger hover:underline text-xs">Hapus</button> </form>
                                @else
                                    <span class="text-xs text-text-secondary">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-4 px-4 text-text-secondary">Tidak ada akun admin.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="bg-bg-surface rounded-xl border border-border p-5 h-fit">
            <h2 class="font-semibold mb-3">Tambah Admin</h2>
            <form method="POST" action="{{ route("admin.system.admins.store") }}" class="space-y-3"> @csrf <input
                    type="text" name="name" required placeholder="Nama lengkap"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> <input
                    type="email" name="email" required placeholder="Email"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> <input
                    type="text" name="identifier" placeholder="Identifier (opsional)"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> <input
                    type="password" name="password" required placeholder="Kata sandi"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                <button
                    class="w-full px-3 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm">Simpan</button>
            </form>
        </div>
    </div>
</div> {{-- Modal reset password --}}
<div id="reset-modal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-bg-surface rounded-lg border border-border p-4 w-full max-w-sm">
        <h3 class="font-semibold mb-3">Reset Password — <span id="reset-name"></span></h3>
        <form method="POST" action="" id="reset-form"> @csrf <div> <label
                    class="block text-sm font-medium mb-1">Kata Sandi Baru</label> <input type="password"
                    name="password" required minlength="6"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
            <div class="flex justify-end gap-2 mt-4"> <button type="button" id="reset-cancel"
                    class="px-3 py-2 rounded-md bg-status-danger hover:bg-status-danger/90 text-white text-sm">Batal</button> <button
                    class="px-3 py-2 rounded-md bg-brand text-white text-sm">Reset</button> </div>
        </form>
    </div>
</div>
@endsection @section("scripts")
<script>
    (function() {
        var modal = document.getElementById('reset-modal');
        var form = document.getElementById('reset-form');
        document.querySelectorAll('.reset-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = btn.dataset.reset;
                document.getElementById('reset-name').textContent = btn.dataset.name;
                form.action = '/admin/system/admins/' + id + '/reset-password';
                modal.classList.remove('hidden');
            });
        });
        document.getElementById('reset-cancel').addEventListener('click', function() {
            modal.classList.add('hidden');
        });
        modal.addEventListener('click', function(e) {
            if (e.target === modal) modal.classList.add('hidden');
        });
    })();
</script>
@endsection