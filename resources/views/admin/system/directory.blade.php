@extends('layouts.app')

@section('title', 'Kelola Struktur Direktori')

@section('content')
<div class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold">Kelola Struktur Direktori</h1>
            <p class="text-sm text-text-secondary">Tambah universitas, fakultas, departemen, dan prodi. Struktur ini dipakai untuk afiliasi dosen/mahasiswa & langganan direktori.</p>
        </div>
        <a href="{{ route('admin.system.directory-subscriptions') }}" class="inline-flex items-center gap-2 px-3 py-2 rounded-md bg-bg-hover hover:bg-border text-text-primary text-sm font-medium transition-colors">
            <span class="material-symbols-outlined icon-md">workspace_premium</span>
            Langganan Direktori
        </a>
    </div>

    <div class="grid lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 space-y-4">
            @forelse ($universities as $univ)
                <div class="bg-bg-surface rounded-xl border border-border p-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-block px-2 py-0.5 rounded-full text-[10px] bg-brand-light text-brand">Universitas</span>
                        <h2 class="font-heading font-semibold text-text-primary">{{ $univ->name }}</h2>
                        <span class="text-xs text-text-secondary">#{{ $univ->id }}</span>
                        <a href="{{ route('admin.system.directory.universities.edit', $univ) }}" class="text-xs text-brand hover:underline ml-1">Edit</a>
                    </div>

                    @forelse ($univ->faculties as $faculty)
                        <div class="mt-4 ml-4 border-l border-border pl-4">
                            <div class="flex items-center gap-2">
                                <span class="inline-block px-2 py-0.5 rounded-full text-[10px] bg-bg-panel">Fakultas</span>
                                <h3 class="font-medium text-text-primary">{{ $faculty->name }}</h3>
                                <span class="text-xs text-text-secondary">#{{ $faculty->id }}</span>
                                <a href="{{ route('admin.system.directory.faculties.edit', $faculty) }}" class="text-xs text-brand hover:underline ml-1">Edit</a>
                            </div>

                            @foreach ($faculty->departments as $dept)
                                <div class="mt-3 ml-4 border-l border-border pl-4">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block px-2 py-0.5 rounded-full text-[10px] bg-bg-panel">Departemen</span>
                                        <h4 class="text-sm font-semibold text-text-secondary">{{ $dept->name }}</h4>
                                        <span class="text-xs text-text-secondary">#{{ $dept->id }}</span>
                                        <a href="{{ route('admin.system.directory.departments.edit', $dept) }}" class="text-xs text-brand hover:underline ml-1">Edit</a>
                                    </div>

                                    @if ($dept->studyPrograms->isNotEmpty())
                                        <ul class="mt-2 ml-4 space-y-1">
                                            @foreach ($dept->studyPrograms as $prodi)
                                                <li class="flex items-center gap-2 text-sm">
                                                    <span class="inline-block px-2 py-0.5 rounded-full text-[10px] bg-bg-panel">Prodi</span>
                                                    <span class="text-text-primary">{{ $prodi->name }}</span>
                                                    @if ($prodi->code)
                                                        <span class="text-xs text-text-secondary">({{ $prodi->code }})</span>
                                                    @endif
                                                    <span class="text-xs text-text-secondary">#{{ $prodi->id }}</span>
                                                    <a href="{{ route('admin.system.directory.study-programs.edit', $prodi) }}" class="text-xs text-brand hover:underline">Edit</a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif

                                    <form method="POST" action="{{ route('admin.system.directory.study-programs.store') }}" class="mt-2 flex flex-wrap items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="department_id" value="{{ $dept->id }}">
                                        <input type="text" name="name" placeholder="Tambah prodi..." maxlength="255" required
                                            class="flex-1 min-w-[200px] rounded-md border border-border bg-bg-surface px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                                        <input type="text" name="code" placeholder="Kode (opsional)" maxlength="50"
                                            class="w-32 rounded-md border border-border bg-bg-surface px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                                        <button class="px-3 py-1.5 rounded-md bg-bg-hover hover:bg-border text-text-primary text-xs font-medium">+ Prodi</button>
                                    </form>
                                </div>
                            @endforeach

                            <form method="POST" action="{{ route('admin.system.directory.departments.store') }}" class="mt-3 flex items-center gap-2">
                                @csrf
                                <input type="hidden" name="faculty_id" value="{{ $faculty->id }}">
                                <input type="text" name="name" placeholder="Tambah departemen..." maxlength="255" required
                                    class="flex-1 min-w-[200px] rounded-md border border-border bg-bg-surface px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                                <button class="px-3 py-1.5 rounded-md bg-bg-hover hover:bg-border text-text-primary text-xs font-medium">+ Departemen</button>
                            </form>
                        </div>
                    @empty
                        <p class="mt-3 ml-4 text-sm text-text-secondary italic">Belum ada fakultas.</p>
                    @endforelse

                    <form method="POST" action="{{ route('admin.system.directory.faculties.store') }}" class="mt-4 flex items-center gap-2">
                        @csrf
                        <input type="hidden" name="university_id" value="{{ $univ->id }}">
                        <input type="text" name="name" placeholder="Tambah fakultas..." maxlength="255" required
                            class="flex-1 min-w-[200px] rounded-md border border-border bg-bg-surface px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                        <button class="px-3 py-1.5 rounded-md bg-bg-hover hover:bg-border text-text-primary text-xs font-medium">+ Fakultas</button>
                    </form>
                </div>
            @empty
                <div class="bg-bg-surface rounded-xl border border-border p-8 text-center text-text-secondary">
                    <span class="material-symbols-outlined icon-lg mb-2 text-text-secondary/50">account_balance</span>
                    <p>Belum ada universitas. Tambahkan universitas pertama di panel kanan.</p>
                </div>
            @endforelse
        </div>

        <div class="bg-bg-surface rounded-xl border border-border p-5 h-fit">
            <h2 class="font-semibold mb-3">Tambah Universitas</h2>
            <form method="POST" action="{{ route('admin.system.directory.universities.store') }}" class="space-y-3">
                @csrf

                <div>
                    <label class="block text-sm mb-1">Nama Universitas</label>
                    {{-- Autocomplete: sisipkan nama universitas yang sudah ada agar
                         system admin bisa memilih entri existing (mencegah double). --}}
                    <input type="text" name="name" value="{{ old('name') }}" maxlength="255" required
                        list="existing-universities" placeholder="Mulai ketik atau pilih..."
                        class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                    <datalist id="existing-universities">
                        @foreach ($universities as $u)
                            <option value="{{ $u->name }}"></option>
                        @endforeach
                    </datalist>
                    <p id="univ-duplicate-hint" class="hidden text-xs text-status-pending mt-1">Nama ini sudah ada — memilih akan menggunakan entri yang sudah terdaftar (tidak membuat duplikat).</p>
                    @error('name')<p class="text-xs text-status-danger mt-1">{{ $message }}</p>@enderror
                </div>

                <button class="w-full px-3 py-2 rounded-md bg-brand hover:bg-brand-hover text-[#0b1420] text-sm font-medium">+ Tambah Universitas</button>

                <p class="text-xs text-text-secondary">Nama duplikat akan otomatis di-merge (mengikuti entri yang sudah ada).</p>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    (function() {
        var input = document.querySelector('input[name="name"][list="existing-universities"]');
        var hint = document.getElementById('univ-duplicate-hint');
        if (!input || !hint) return;

        // Kumpulkan nama universitas yang sudah ada (dari datalist).
        var existing = Array.from(document.querySelectorAll('#existing-universities option')).map(function(o) {
            return o.value.trim().toLowerCase();
        });

        function checkDuplicate() {
            var v = input.value.trim().toLowerCase();
            if (v.length > 0 && existing.indexOf(v) !== -1) {
                hint.classList.remove('hidden');
            } else {
                hint.classList.add('hidden');
            }
        }

        input.addEventListener('input', checkDuplicate);
        // Jalankan sekali saat halaman dimuat (mis. karena old() input setelah error).
        checkDuplicate();
    })();
</script>
@endsection
