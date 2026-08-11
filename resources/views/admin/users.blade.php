@extends('layouts.app')
@section('title', 'Kelola Pengguna')

@section('content')
@php
    $me = auth()->user();
    $isSystemAdmin = $me->isSystemAdmin();
    $showInstitutionCol = $isSystemAdmin; // system admin lihat kolom institusi
@endphp
<div class="space-y-4">
    {{-- Header + tabs --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold">Kelola Pengguna</h1>
            <p class="text-sm text-text-secondary">
                @if ($isSystemAdmin)
                    System Admin: kelola semua pengguna lintas institusi, paket, dan struktur direktori.
                @else
                    Kelola dosen &amp; mahasiswa di institusi Anda. Aksi dibatasi pada akun dalam cakupan Anda.
                @endif
            </p>
        </div>
        <a href="{{ route('admin.system.settings') }}" class="inline-flex items-center gap-2 px-3 py-2 rounded-md bg-bg-hover hover:bg-border text-text-primary text-sm font-medium">
            <span class="material-symbols-outlined icon-md">settings</span>
            Pengaturan Autentikasi
        </a>
    </div>

    {{-- Tabs (konteks), hanya untuk system admin --}}
    @if ($isSystemAdmin)
        <div class="flex gap-1 border-b border-border">
            <a href="{{ request()->fullUrlWithQuery(['tab' => 'mine']) }}"
                class="px-4 py-2 text-sm font-medium border-b-2 {{ ($tab ?? 'mine') === 'mine' ? 'border-brand text-brand' : 'border-transparent text-text-secondary hover:text-text-primary' }}">
                Pengguna
            </a>
            <a href="{{ request()->fullUrlWithQuery(['tab' => 'all']) }}"
                class="px-4 py-2 text-sm font-medium border-b-2 {{ ($tab ?? 'mine') === 'all' ? 'border-brand text-brand' : 'border-transparent text-text-secondary hover:text-text-primary' }}">
                Semua Pengguna
            </a>
        </div>
    @endif

    {{-- Locked banner untuk admin tanpa scope --}}
    @if (! empty($isLockedNonSystemAdmin))
        <div class="px-4 py-4 rounded-xl bg-status-pending/10 border border-status-pending/30 text-sm text-status-pending">
            <p class="font-semibold mb-1">Akun Anda belum memiliki scope admin.</p>
            <p>Anda tidak dapat melihat atau mengelola pengguna sampai system admin memberikan cakupan (fakultas/departemen/prodi). Hubungi system admin untuk informasi lebih lanjut.</p>
        </div>
    @endif

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-bg-surface rounded-xl border border-border p-3">
            <p class="text-xs text-text-secondary">Total</p>
            <p class="text-2xl font-bold text-text-primary">{{ $counts['total'] ?? 0 }}</p>
        </div>
        <div class="bg-bg-surface rounded-xl border border-border p-3">
            <p class="text-xs text-text-secondary">Dosen</p>
            <p class="text-2xl font-bold text-text-primary">{{ $counts['dosen'] ?? 0 }}</p>
        </div>
        <div class="bg-bg-surface rounded-xl border border-border p-3">
            <p class="text-xs text-text-secondary">Mahasiswa</p>
            <p class="text-2xl font-bold text-text-primary">{{ $counts['mahasiswa'] ?? 0 }}</p>
        </div>
        <div class="bg-bg-surface rounded-xl border border-border p-3">
            <p class="text-xs text-text-secondary">Ditolak</p>
            <p class="text-2xl font-bold text-text-primary">{{ $counts['ditolak'] ?? 0 }}</p>
        </div>
    </div>

    {{-- Search / filter --}}
    <form method="GET" action="{{ route('admin.users') }}" class="bg-bg-surface rounded-xl border border-border p-4 flex flex-wrap gap-3">
        @if (! empty($tab))
            <input type="hidden" name="tab" value="{{ $tab }}">
        @endif
        <input type="text" name="keyword" value="{{ request('keyword') }}" placeholder="Nama / email / identifier"
            class="w-full sm:w-auto rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
        <select name="role" class="w-full sm:w-auto rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
            <option value="">Semua role</option>
            @foreach ($roles as $r)
                <option value="{{ $r->name }}" @selected(request('role') === $r->name)>{{ ucfirst($r->name) }}</option>
            @endforeach
        </select>
        <select name="status" class="w-full sm:w-auto rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
            <option value="">Semua status</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="verified" @selected(request('status') === 'verified')>Verified</option>
            <option value="rejected" @selected(request('status') === 'rejected')>Rejected</option>
            <option value="pending" @selected(request('status') === 'pending')>Pending</option>
        </select>
        @if ($isSystemAdmin)
            <select name="institution_id" class="w-full sm:w-auto rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                <option value="">Semua institusi</option>
                <option value="none" @selected(request('institution_id') === 'none')>— Personal —</option>
                @foreach ($institutions as $inst)
                    <option value="{{ $inst->id }}" @selected((string) request('institution_id') === (string) $inst->id)>{{ $inst->institution_name }}</option>
                @endforeach
            </select>
        @endif
        <select name="verified" class="w-full sm:w-auto rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
            <option value="">Email semua</option>
            <option value="1" @selected(request('verified') === '1')>Terverifikasi</option>
            <option value="0" @selected(request('verified') === '0')>Belum</option>
        </select>
        <select name="sort" class="w-full sm:w-auto rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
            <option value="latest" @selected(request('sort', 'latest') === 'latest')>Terbaru</option>
            <option value="name" @selected(request('sort') === 'name')>Nama (A-Z)</option>
        </select>
        <button class="w-full sm:w-auto px-3 py-2 rounded-md bg-brand text-[#0b1420] text-sm font-semibold">Cari</button>
        <a href="{{ route('admin.users', request()->only('tab')) }}" class="w-full sm:w-auto px-3 py-2 rounded-md bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">Reset</a>
        <a href="{{ route('admin.users.export', request()->query()) }}" class="w-full sm:w-auto px-3 py-2 rounded-md bg-bg-hover text-text-primary text-sm font-medium hover:bg-border inline-flex items-center gap-1.5">
            <span class="material-symbols-outlined icon-sm">download</span> Export CSV
        </a>
    </form>

    <div class="grid lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 bg-bg-surface rounded-xl border border-border overflow-x-auto">
            {{-- Bulk action toolbar (muncul saat ada checkbox dipilih via JS) --}}
            <div id="bulk-toolbar" class="hidden px-4 py-2 border-b border-border bg-bg-panel flex flex-wrap items-center gap-3 text-sm">
                <span class="text-text-secondary"><span id="bulk-count">0</span> dipilih</span>
                <select id="bulk-action" class="rounded-md border border-border bg-bg-surface px-2 py-1 text-sm">
                    <option value="approve">Setujui</option>
                    <option value="reject">Tolak</option>
                    <option value="delete">Hapus</option>
                </select>
                <button type="button" id="bulk-apply" class="px-3 py-1.5 rounded-md bg-brand text-[#0b1420] text-sm font-semibold">Terapkan</button>
                <button type="button" id="bulk-cancel" class="px-3 py-1.5 rounded-md bg-bg-hover text-text-primary text-sm">Batal</button>
            </div>
            <form id="bulk-form" method="POST" action="{{ route('admin.users.bulk') }}">
                @csrf
                <input type="hidden" name="action" id="bulk-action-input" value="">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border">
                            <th class="py-3 px-4 w-8"><input type="checkbox" id="bulk-all" class="rounded bg-bg-surface"></th>
                            <th class="py-3 px-4">Nama</th>
                            <th class="py-3 px-4 table-col-nim">NIM/NIDN</th>
                            <th class="py-3 px-4 table-col-email">Email</th>
                            <th class="py-3 px-4">Role</th>
                            <th class="py-3 px-4">Status</th>
                            @if ($showInstitutionCol)
                                <th class="py-3 px-4">Kuota</th>
                                <th class="py-3 px-4">Institusi</th>
                            @endif
                            <th class="py-3 px-4">Terdaftar</th>
                            <th class="py-3 px-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $u)
                            <tr class="border-b border-border">
                                <td class="py-3 px-4">
                                    <input type="checkbox" name="ids[]" value="{{ $u->id }}" class="bulk-check rounded bg-bg-surface">
                                </td>
                                <td class="py-3 px-4">
                                    {{ $u->name }}
                                    @php $uUniv = $u->primaryUniversity(); @endphp
                                    @if ($uUniv)
                                        <span class="block text-[10px] text-text-secondary mt-0.5">{{ $uUniv->name }}</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 table-col-nim font-mono">{{ $u->nim ?? '—' }}</td>
                                <td class="py-3 px-4 table-col-email">
                                    <div class="flex items-center gap-1.5">
                                        <span>{{ $u->email }}</span>
                                        @if ($u->email_verified_at)
                                            <span class="inline-block px-1.5 py-0.5 rounded text-[10px] bg-status-success/10 text-status-success" title="Email terverifikasi">✓</span>
                                        @else
                                            <span class="inline-block px-1.5 py-0.5 rounded text-[10px] bg-status-pending/10 text-status-pending" title="Email belum diverifikasi">!</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    @foreach ($u->roles->whereNotIn('name', ['admin', 'system_admin']) as $role)
                                        <span class="inline-block px-2 py-0.5 rounded-full text-xs bg-bg-panel mr-1">{{ $role->name }}</span>
                                    @endforeach
                                </td>
                                <td class="py-3 px-4">
                                    @php
                                        $status = $u->registration_status ?? 'active';
                                        $statusColor = match($status) {
                                            'active' => 'bg-status-success/10 text-status-success',
                                            'verified' => 'bg-accent-blue/10 text-accent-blue',
                                            'rejected' => 'bg-status-danger/10 text-status-danger',
                                            default => 'bg-status-pending/10 text-status-pending',
                                        };
                                    @endphp
                                    <span class="inline-block px-2 py-0.5 rounded-full text-xs {{ $statusColor }}">{{ ucfirst($status) }}</span>
                                </td>
                                @if ($showInstitutionCol)
                                    <td class="py-3 px-4 text-xs">
                                        @php $q = $quotaMap->get($u->id); @endphp
                                        @if ($q && $q['has_override'])
                                            <span class="inline-block px-1.5 py-0.5 rounded text-[10px] bg-accent-purple/10 text-accent-purple">override</span>
                                            <span class="text-text-primary">{{ $q['override_mb'] }} MB</span>
                                        @else
                                            <span class="text-text-secondary">{{ $q ? $q['effective_mb'] : '—' }} MB</span>
                                            <span class="block text-[10px] text-text-secondary/70">ikut paket/pool</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-xs text-text-secondary">
                                        @if ($u->institution_id)
                                            {{ optional($institutions->firstWhere('id', $u->institution_id))->institution_name ?? '—' }}
                                        @else
                                            <span class="italic">Personal</span>
                                        @endif
                                    </td>
                                @endif
                                <td class="py-3 px-4 text-xs text-text-secondary">
                                    {{ $u->created_at?->format('d M Y') ?? '—' }}
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                        @if ($isSystemAdmin)
                                            <a href="{{ route('admin.system.users.plan', $u) }}" class="text-brand hover:underline text-xs">Paket &amp; Kuota</a>
                                            <button type="button" data-quota="{{ $u->id }}" data-name="{{ $u->name }}" data-mb="{{ $quotaMap->get($u->id)['override_mb'] ?? '' }}" class="quota-btn text-brand hover:underline text-xs">Set Kuota</button>
                                            <form method="POST" action="{{ route('admin.system.users.institution', $u) }}" class="inline-flex items-center gap-1">
                                                @csrf
                                                <select name="institution_id" onchange="if(confirm('Ubah institusi user ini?')){this.form.submit()}else{this.value='{{ $u->institution_id ?? '' }}'}" class="text-xs rounded border border-border bg-bg-surface px-1 py-0.5">
                                                    <option value="">Personal</option>
                                                    @foreach ($institutions as $inst)
                                                        <option value="{{ $inst->id }}" @selected($u->institution_id === $inst->id)>{{ $inst->institution_name }}</option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        @elseif ($me->isAdmin())
                                            @if ($u->isDosen())
                                                <a href="{{ route('admin.dosen.kuota', $u) }}" class="text-brand hover:underline text-xs">Kuota</a>
                                            @endif
                                        @endif
                                        @if ($isSystemAdmin || (! $u->isAdmin() && ! $u->isSystemAdmin()))
                                            <button type="button" data-reset="{{ $u->id }}" data-name="{{ $u->name }}" class="reset-btn text-brand hover:underline text-xs">Reset PW</button>
                                            <form method="POST" action="{{ route('admin.users.destroy', $u) }}" onsubmit="return confirm('Hapus pengguna ini?')" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button class="text-status-danger hover:underline text-xs">Hapus</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $showInstitutionCol ? 10 : 8 }}" class="py-4 px-4 text-text-secondary">
                                    @if (! empty($isLockedNonSystemAdmin))
                                        Tidak dapat menampilkan pengguna — scope admin belum diatur.
                                    @else
                                        Tidak ada pengguna.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </form>
            <div class="p-3">{{ $users->links() }}</div>
        </div>

        <div class="bg-bg-surface rounded-xl border border-border p-5 h-fit space-y-4">
            <h2 class="font-semibold">Tambah Pengguna</h2>
            <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-3">
                @csrf
                <input type="text" name="name" required placeholder="Nama lengkap" class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                <input type="email" name="email" required placeholder="Email" class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                <input type="text" name="nim" placeholder="NIM / NIDN" class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                <input type="password" name="password" required placeholder="Kata sandi" class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                <div>
                    <label class="block text-sm mb-1">Role</label>
                    <div class="flex gap-3">
                        @foreach (["admin", "dosen", "mahasiswa"] as $r)
                            @if ($r === 'admin' && ! $isSystemAdmin)
                                @continue
                            @endif
                            <label class="flex items-center gap-1 text-sm">
                                <input type="checkbox" name="roles[]" value="{{ $r }}" class="rounded bg-bg-surface">
                                {{ ucfirst($r) }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <button class="w-full px-3 py-2 rounded-md bg-brand hover:bg-brand-hover text-[#0b1420] text-sm font-semibold">Simpan</button>
            </form>

            @if ($me->hasRole('admin') && ! $isSystemAdmin && $me->adminScopes->isNotEmpty())
                <div class="border-t border-border pt-4">
                    <h2 class="font-semibold mb-1">Tambah Admin (Sub-Admin)</h2>
                    <p class="text-xs text-text-secondary mb-3">Buat admin di bawah cakupan scope Anda. Pilih universitas → fakultas → departemen → prodi.</p>
                    <form method="POST" action="{{ route('admin.sub-admins.store') }}" class="space-y-3">
                        @csrf
                        <input type="text" name="name" required placeholder="Nama lengkap" class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                        <input type="email" name="email" required placeholder="Email" class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                        <input type="text" name="nim" placeholder="Identifier (opsional)" class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                        <input type="password" name="password" required placeholder="Kata sandi" class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                        <div>
                            <label class="block text-sm mb-1">Scope Admin</label>
                            <div id="sub-scope-list" class="space-y-2"></div>
                            <button type="button" id="add-sub-scope" class="mt-2 text-xs text-brand hover:underline">+ Tambah scope</button>
                        </div>
                        <button class="w-full px-3 py-2 rounded-md bg-brand hover:bg-brand-hover text-[#0b1420] text-sm font-semibold">Simpan Admin</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Modal reset password --}}
<div id="reset-modal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-bg-surface rounded-lg border border-border p-4 w-full max-w-sm">
        <h3 class="font-semibold mb-3">Reset Password — <span id="reset-name"></span></h3>
        <form method="POST" action="" id="reset-form">
            @csrf
            <div>
                <label class="block text-sm font-medium mb-1">Kata Sandi Baru</label>
                <input type="password" name="password" required minlength="6" class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" id="reset-cancel" class="px-3 py-2 rounded-md bg-status-danger hover:status-danger/90 text-white text-sm">Batal</button>
                <button class="px-3 py-2 rounded-md bg-brand text-[#0b1420] text-sm">Reset</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal set kuota (system admin) --}}
<div id="quota-modal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-bg-surface rounded-lg border border-border p-4 w-full max-w-sm">
        <h3 class="font-semibold mb-1">Set Kuota Individu — <span id="quota-name"></span></h3>
        <p class="text-xs text-text-secondary mb-3">Override kuota workspace (MB) yang menggantikan paket/pool. Kosongkan atau 0 untuk mengikuti paket/pool.</p>
        <form method="POST" action="" id="quota-form">
            @csrf
            <div>
                <label class="block text-sm font-medium mb-1">Batas Workspace (MB)</label>
                <input type="number" name="storage_limit_mb" min="0" max="1048576" id="quota-mb"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm" placeholder="Kosongkan = ikut paket/pool">
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" id="quota-cancel" class="px-3 py-2 rounded-md bg-status-danger hover:status-danger/90 text-white text-sm">Batal</button>
                <button class="px-3 py-2 rounded-md bg-brand text-[#0b1420] text-sm">Simpan Kuota</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function() {
    // ---------- Reset password modal ----------
    var modal = document.getElementById('reset-modal');
    var form = document.getElementById('reset-form');
    var nameEl = document.getElementById('reset-name');
    var cancelBtn = document.getElementById('reset-cancel');
    document.querySelectorAll('.reset-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = btn.getAttribute('data-reset');
            var name = btn.getAttribute('data-name');
            form.action = "{{ url('admin/users') }}/" + id + "/reset-password";
            nameEl.textContent = name;
            modal.classList.remove('hidden');
        });
    });
    if (cancelBtn) cancelBtn.addEventListener('click', function() { modal.classList.add('hidden'); });

    // ---------- Set Kuota modal (system admin) ----------
    var quotaModal = document.getElementById('quota-modal');
    var quotaForm = document.getElementById('quota-form');
    var quotaName = document.getElementById('quota-name');
    var quotaMb = document.getElementById('quota-mb');
    var quotaCancel = document.getElementById('quota-cancel');
    if (quotaForm && quotaModal) {
        document.querySelectorAll('.quota-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = btn.getAttribute('data-quota');
                var name = btn.getAttribute('data-name');
                quotaForm.action = "{{ url('admin/system/users') }}/" + id + "/quota";
                quotaName.textContent = name;
                quotaMb.value = btn.getAttribute('data-mb') || '';
                quotaModal.classList.remove('hidden');
            });
        });
        if (quotaCancel) quotaCancel.addEventListener('click', function() { quotaModal.classList.add('hidden'); });
    }

    // ---------- Bulk action toolbar ----------
    var toolbar = document.getElementById('bulk-toolbar');
    var countEl = document.getElementById('bulk-count');
    var actionSel = document.getElementById('bulk-action');
    var actionInput = document.getElementById('bulk-action-input');
    var applyBtn = document.getElementById('bulk-apply');
    var cancelBulk = document.getElementById('bulk-cancel');
    var checks = Array.from(document.querySelectorAll('.bulk-check'));
    var allBox = document.getElementById('bulk-all');
    var bulkForm = document.getElementById('bulk-form');

    function updateBulkToolbar() {
        var checked = checks.filter(function(c) { return c.checked; });
        countEl.textContent = String(checked.length);
        if (checked.length > 0) { toolbar.classList.remove('hidden'); }
        else { toolbar.classList.add('hidden'); }
    }
    if (allBox) {
        allBox.addEventListener('change', function() {
            checks.forEach(function(c) { c.checked = allBox.checked; });
            updateBulkToolbar();
        });
    }
    checks.forEach(function(c) {
        c.addEventListener('change', updateBulkToolbar);
    });
    if (applyBtn) {
        applyBtn.addEventListener('click', function() {
            var action = actionSel.value;
            var labels = { approve: 'menyetujui', reject: 'menolak', delete: 'menghapus' };
            if (!confirm('Yakin ingin ' + labels[action] + ' ' + countEl.textContent + ' user?')) return;
            actionInput.value = action;
            // Submit form normally (POST ke admin.users.bulk).
            bulkForm.submit();
        });
    }
    if (cancelBulk) {
        cancelBulk.addEventListener('click', function() {
            checks.forEach(function(c) { c.checked = false; });
            if (allBox) allBox.checked = false;
            updateBulkToolbar();
        });
    }

    // ---------- Sub-admin scope dropdown (hierarki) ----------
    var subList = document.getElementById('sub-scope-list');
    var addBtn = document.getElementById('add-sub-scope');
    if (subList && addBtn) {
        var tree = @json(\App\Models\University::with('faculties.departments.studyPrograms')->orderBy('name')->get());
        var scopeIdx = 0;
        function renderRow() {
            var row = document.createElement('div');
            row.className = 'flex flex-wrap gap-2 items-center';
            row.innerHTML = ''
                + '<select name="scopes[' + scopeIdx + '][scope_type]" class="sub-type w-full sm:w-auto rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">'
                + '  <option value="university">Universitas</option>'
                + '  <option value="faculty">Fakultas</option>'
                + '  <option value="department">Departemen</option>'
                + '  <option value="study_program">Prodi</option>'
                + '</select>'
                + '<select name="scopes[' + scopeIdx + '][scope_id]" class="sub-node w-full sm:flex-1 rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"></select>'
                + '<button type="button" class="remove-scope px-2 py-2 rounded-md bg-status-danger/10 text-status-danger text-xs">Hapus</button>';
            subList.appendChild(row);
            scopeIdx++;

            var typeEl = row.querySelector('.sub-type');
            var nodeEl = row.querySelector('.sub-node');
            function refreshNodes() {
                var t = typeEl.value;
                nodeEl.innerHTML = '';
                if (t === 'university') {
                    tree.forEach(function(u) { var o = document.createElement('option'); o.value = u.id; o.textContent = u.name; nodeEl.appendChild(o); });
                } else if (t === 'faculty') {
                    tree.forEach(function(u) { (u.faculties || []).forEach(function(f) { var o = document.createElement('option'); o.value = f.id; o.textContent = u.name + ' → ' + f.name; nodeEl.appendChild(o); }); });
                } else if (t === 'department') {
                    tree.forEach(function(u) { (u.faculties || []).forEach(function(f) { (f.departments || []).forEach(function(d) { var o = document.createElement('option'); o.value = d.id; o.textContent = u.name + ' → ' + f.name + ' → ' + d.name; nodeEl.appendChild(o); }); }); });
                } else if (t === 'study_program') {
                    tree.forEach(function(u) { (u.faculties || []).forEach(function(f) { (f.departments || []).forEach(function(d) { (d.study_programs || []).forEach(function(p) { var o = document.createElement('option'); o.value = p.id; o.textContent = u.name + ' → ' + f.name + ' → ' + d.name + ' → ' + p.name; nodeEl.appendChild(o); }); }); }); });
                }
            }
            typeEl.addEventListener('change', refreshNodes);
            refreshNodes();

            row.querySelector('.remove-scope').addEventListener('click', function() { row.remove(); });
        }
        addBtn.addEventListener('click', renderRow);
        renderRow();
    }
})();
</script>
@endsection
