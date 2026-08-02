@extends("layouts.app") @section("title", "Kelola Pengguna") @section("content")
<div class="space-y-4">
    <h1 class="text-xl font-bold">Kelola Pengguna</h1> {{-- Search / filter --}} <form method="GET"
        action="{{ route("admin.users") }}"
        class="bg-bg-surface rounded-xl border border-border p-4 flex flex-wrap gap-3"> <input type="text"
            name="keyword" value="{{ request("keyword") }}" placeholder="Nama / email / identifier"
            class="rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> <select name="role"
            class="rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
            <option value="">Semua role</option>
            @foreach ($roles as $r)
                <option value="{{ $r->name }}" @selected(request("role") === $r->name)>{{ ucfirst($r->name) }}</option>
            @endforeach
        </select> <select name="sort" class="rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
            <option value="latest" @selected(request("sort") === "latest")>Terbaru</option>
            <option value="name" @selected(request("sort") === "name")>Nama (A-Z)</option>
        </select> <button class="px-3 py-2 rounded-md bg-accent-teal text-white text-sm">Cari</button> </form>
    <div class="grid lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 bg-bg-surface rounded-xl border border-border overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-text-secondary border-b border-border">
                        <th class="py-3 px-4">Nama</th>
                        <th class="py-3 px-4">Identifier</th>
                        <th class="py-3 px-4">Email</th>
                        <th class="py-3 px-4">Role</th>
                        <th class="py-3 px-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $u)
                        <tr class="border-b border-border">
                            <td class="py-3 px-4">{{ $u->name }}</td>
                            <td class="py-3 px-4">{{ $u->identifier ?? "—" }}</td>
                            <td class="py-3 px-4">{{ $u->email }}</td>
                            <td class="py-3 px-4">
                                @foreach ($u->roles as $role)
                                    <span
                                        class="inline-block px-2 py-0.5 rounded-full text-xs bg-bg-panel mr-1">{{ $role->name }}</span>
                                @endforeach
                            </td>
                            <td class="py-3 px-4"> <button type="button" data-reset="{{ $u->id }}"
                                    data-name="{{ $u->name }}"
                                    class="reset-btn text-accent-blue hover:underline text-xs mr-2">Reset PW</button>
                                <form method="POST" action="{{ route("admin.users.destroy", $u) }}"
                                    onsubmit="return confirm('Hapus pengguna ini?')" class="inline"> @csrf
                                    @method("DELETE") <button
                                        class="text-status-danger hover:underline text-xs">Hapus</button> </form>
                            </td>
                    </tr> @empty <tr>
                            <td colspan="5" class="py-4 px-4 text-text-secondary">Tidak ada pengguna.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-3">{{ $users->links() }}</div>
        </div>
        <div class="bg-bg-surface rounded-xl border border-border p-5 h-fit">
            <h2 class="font-semibold mb-3">Tambah Pengguna</h2>
            <form method="POST" action="{{ route("admin.users.store") }}" class="space-y-3"> @csrf <input
                    type="text" name="name" required placeholder="Nama lengkap"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> <input
                    type="email" name="email" required placeholder="Email"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> <input
                    type="text" name="identifier" placeholder="NIM / NIDN"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> <input
                    type="password" name="password" required placeholder="Kata sandi"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                <div> <label class="block text-sm mb-1">Role</label>
                    <div class="flex gap-3">
                        @foreach (["admin", "dosen", "mahasiswa"] as $r)
                            <label class="flex items-center gap-1 text-sm"><input type="checkbox" name="roles[]"
                                    value="{{ $r }}" class="rounded bg-bg-surface">
                                {{ ucfirst($r) }}</label>
                        @endforeach
                    </div>
                </div> <button
                    class="w-full px-3 py-2 rounded-md bg-accent-teal hover:bg-accent-teal/90 text-white text-sm">Simpan</button>
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
                    class="px-3 py-2 rounded-md bg-bg-panel text-sm">Batal</button> <button
                    class="px-3 py-2 rounded-md bg-accent-blue text-white text-sm">Reset</button> </div>
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
                form.action = '/admin/users/' + id + '/reset-password';
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
