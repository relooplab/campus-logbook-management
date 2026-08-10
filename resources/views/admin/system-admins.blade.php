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
                                @if ($u->institution)
                                    <span class="block text-[10px] text-text-secondary mt-0.5">{{ $u->institution->institution_name }}</span>
                                @endif
                                @if ($u->adminScopes->isNotEmpty())
                                    <span class="block text-[10px] text-brand mt-0.5">{{ $u->adminScopes->count() }} scope</span>
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

                <div>
                    <label class="block text-sm mb-1">Institusi</label>
                    <select name="institution_id" class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                        <option value="">— Personal (tanpa institusi) —</option>
                        @foreach ($institutions as $inst)
                            <option value="{{ $inst->id }}">{{ $inst->institution_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm mb-1">Scope Admin (opsional)</label>
                    <p class="text-xs text-text-secondary mb-2">Kosongkan = institusi penuh. Pilih prodi/departemen/fakultas untuk membatasi cakupan.</p>
                    <div id="scope-list" class="space-y-2">
                        <div class="flex gap-2">
                            <select name="scopes[0][scope_type]" class="scope-type w-1/3 rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                                <option value="study_program">Prodi</option>
                                <option value="department">Departemen</option>
                                <option value="faculty">Fakultas</option>
                            </select>
                            <input type="number" name="scopes[0][scope_id]" placeholder="ID node" class="w-1/3 rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                            <button type="button" class="remove-scope px-2 py-2 rounded-md bg-status-danger/10 text-status-danger text-xs">Hapus</button>
                        </div>
                    </div>
                    <button type="button" id="add-scope" class="mt-2 text-xs text-brand hover:underline">+ Tambah scope</button>
                </div>

                <button
                    class="w-full px-3 py-2 rounded-md bg-brand hover:bg-brand-hover text-[#0b1420] text-sm">Simpan</button>
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
                    class="px-3 py-2 rounded-md bg-brand text-[#0b1420] text-sm">Reset</button> </div>
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

        // Scope admin dynamic rows.
        var scopeList = document.getElementById('scope-list');
        var addScopeBtn = document.getElementById('add-scope');
        if (scopeList && addScopeBtn) {
            var scopeIndex = 1;
            addScopeBtn.addEventListener('click', function() {
                var row = document.createElement('div');
                row.className = 'flex gap-2';
                row.innerHTML = '<select name="scopes[' + scopeIndex + '][scope_type]" class="scope-type w-1/3 rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">' +
                    '<option value="study_program">Prodi</option>' +
                    '<option value="department">Departemen</option>' +
                    '<option value="faculty">Fakultas</option></select>' +
                    '<input type="number" name="scopes[' + scopeIndex + '][scope_id]" placeholder="ID node" class="w-1/3 rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">' +
                    '<button type="button" class="remove-scope px-2 py-2 rounded-md bg-status-danger/10 text-status-danger text-xs">Hapus</button>';
                scopeList.appendChild(row);
                scopeIndex++;
            });
            scopeList.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-scope')) {
                    e.target.closest('.flex').remove();
                }
            });
        }
    })();
</script>
@endsection