@extends("layouts.app") @section("title", "Kelola Pengguna") @section("content")
<div class="space-y-4">
    <h1 class="text-xl font-bold">Kelola Pengguna</h1> {{-- Search / filter --}} <form method="GET"
        action="{{ route("admin.users") }}"
        class="bg-bg-surface rounded-xl border border-border p-4 flex flex-wrap gap-3"> <input type="text"
            name="keyword" value="{{ request("keyword") }}" placeholder="Nama / email / identifier"
            class="w-full sm:w-auto rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> <select name="role"
            class="w-full sm:w-auto rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
            <option value="">Semua role</option>
            @foreach ($roles as $r)
                <option value="{{ $r->name }}" @selected(request("role") === $r->name)>{{ ucfirst($r->name) }}</option>
            @endforeach
        </select> <select name="sort" class="w-full sm:w-auto rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
            <option value="latest" @selected(request("sort") === "latest")>Terbaru</option>
            <option value="name" @selected(request("sort") === "name")>Nama (A-Z)</option>
        </select> <button class="w-full sm:w-auto px-3 py-2 rounded-md bg-brand text-[#0b1420] text-sm">Cari</button> </form>
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
                    @forelse ($users as $u)
                        <tr class="border-b border-border">
                            <td class="py-3 px-4">
                                {{ $u->name }}
                                @php $uUniv = $u->primaryUniversity(); @endphp
                                @if ($uUniv)
                                    <span class="block text-[10px] text-text-secondary mt-0.5">{{ $uUniv->name }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 table-col-identifier font-mono">{{ $u->identifier ?? "—" }}</td>
                            <td class="py-3 px-4 table-col-email">{{ $u->email }}</td>
                            <td class="py-3 px-4">
                                @foreach ($u->roles->whereNotIn('name', ['admin', 'system_admin']) as $role)
                                    <span
                                        class="inline-block px-2 py-0.5 rounded-full text-xs bg-bg-panel mr-1">{{ $role->name }}</span>
                                @endforeach
                            </td>
                            <td class="py-3 px-4">
                                @if (auth()->user()->isSystemAdmin())
                                    <a href="{{ route('admin.system.users.plan', $u) }}" class="text-brand hover:underline text-xs mr-2">Paket</a>
                                    <form method="POST" action="{{ route('admin.users.institution', $u) }}" class="inline-flex items-center gap-1 mr-2">
                                        @csrf
                                        <select name="institution_id" onchange="this.form.submit()" class="text-xs rounded border border-border bg-bg-surface px-1 py-0.5">
                                            <option value="">Personal</option>
                                            @foreach ($institutions ?? [] as $inst)
                                                <option value="{{ $inst->id }}" @selected($u->institution_id === $inst->id)>{{ $inst->institution_name }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                @endif
@if (auth()->user()->isAdmin())
                                    @if ($u->isDosen())
                                        <a href="{{ route('admin.dosen.kuota', $u) }}" class="text-brand hover:underline text-xs mr-2">Kuota</a>
                                    @endif
                                @endif
                                @if (auth()->user()->isSystemAdmin() || (!$u->isAdmin() && !$u->isSystemAdmin()))
                                    <button type="button" data-reset="{{ $u->id }}"
                                        data-name="{{ $u->name }}"
                                        class="reset-btn text-brand hover:underline text-xs mr-2">Reset PW</button>
                                    <form method="POST" action="{{ route("admin.users.destroy", $u) }}"
                                        onsubmit="return confirm('Hapus pengguna ini?')" class="inline"> @csrf
                                        @method("DELETE") <button
                                            class="text-status-danger hover:underline text-xs">Hapus</button> </form>
                                @endif
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
                            @if ($r === 'admin' && !auth()->user()->isSystemAdmin())
                                @continue
                            @endif
                            <label class="flex items-center gap-1 text-sm"><input type="checkbox" name="roles[]"
                                    value="{{ $r }}" class="rounded bg-bg-surface">
                                {{ ucfirst($r) }}</label>
                        @endforeach
                    </div>
                </div> <button
                    class="w-full px-3 py-2 rounded-md bg-brand hover:bg-brand-hover text-[#0b1420] text-sm">Simpan</button>
            </form>

            @if (auth()->user()->hasRole('admin') && !auth()->user()->isSystemAdmin() && auth()->user()->adminScopes->isNotEmpty())
                <div class="border-t border-border mt-4 pt-4">
                    <h2 class="font-semibold mb-1">Tambah Admin (Sub-Admin)</h2>
                    <p class="text-xs text-text-secondary mb-3">Buat admin di bawah cakupan scope Anda (fakultas/prodi).</p>
                    <form method="POST" action="{{ route("admin.sub-admins.store") }}" class="space-y-3"> @csrf <input
                            type="text" name="name" required placeholder="Nama lengkap"
                            class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> <input
                            type="email" name="email" required placeholder="Email"
                            class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> <input
                            type="text" name="identifier" placeholder="Identifier (opsional)"
                            class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> <input
                            type="password" name="password" required placeholder="Kata sandi"
                            class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                        <div>
                            <label class="block text-sm mb-1">Scope Admin</label>
                            <div id="sub-scope-list" class="space-y-2">
                                <div class="flex gap-2">
                                    <select name="scopes[0][scope_type]" class="sub-scope-type w-1/3 rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                                        <option value="study_program">Prodi</option>
                                        <option value="department">Departemen</option>
                                        <option value="faculty">Fakultas</option>
                                    </select>
                                    <input type="number" name="scopes[0][scope_id]" placeholder="ID node" class="w-1/3 rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                                    <button type="button" class="remove-sub-scope px-2 py-2 rounded-md bg-status-danger/10 text-status-danger text-xs">Hapus</button>
                                </div>
                            </div>
                            <button type="button" id="add-sub-scope" class="mt-2 text-xs text-brand hover:underline">+ Tambah scope</button>
                        </div>
                        <button class="w-full px-3 py-2 rounded-md bg-brand hover:bg-brand-hover text-[#0b1420] text-sm">Simpan Admin</button>
                    </form>
                </div>
            @endif
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

        // Anti dual-role: admin & dosen saling eksklusif.
        var roleCheckboxes = document.querySelectorAll('input[name="roles[]"]');
        roleCheckboxes.forEach(function(cb) {
            cb.addEventListener('change', function() {
                if (cb.checked && cb.value === 'admin') {
                    roleCheckboxes.forEach(function(other) {
                        if (other.value === 'dosen') other.checked = false;
                    });
                }
                if (cb.checked && cb.value === 'dosen') {
                    roleCheckboxes.forEach(function(other) {
                        if (other.value === 'admin') other.checked = false;
                    });
                }
            });
        });

        // Sub-admin scope dynamic rows.
        var subScopeList = document.getElementById('sub-scope-list');
        var addSubScopeBtn = document.getElementById('add-sub-scope');
        if (subScopeList && addSubScopeBtn) {
            var subScopeIndex = 1;
            addSubScopeBtn.addEventListener('click', function() {
                var row = document.createElement('div');
                row.className = 'flex gap-2';
                row.innerHTML = '<select name="scopes[' + subScopeIndex + '][scope_type]" class="sub-scope-type w-1/3 rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">' +
                    '<option value="study_program">Prodi</option>' +
                    '<option value="department">Departemen</option>' +
                    '<option value="faculty">Fakultas</option></select>' +
                    '<input type="number" name="scopes[' + subScopeIndex + '][scope_id]" placeholder="ID node" class="w-1/3 rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">' +
                    '<button type="button" class="remove-sub-scope px-2 py-2 rounded-md bg-status-danger/10 text-status-danger text-xs">Hapus</button>';
                subScopeList.appendChild(row);
                subScopeIndex++;
            });
            subScopeList.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-sub-scope')) {
                    e.target.closest('.flex').remove();
                }
            });
        }
    })();
</script>
@endsection
