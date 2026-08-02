@extends("layouts.app") @section("title", "Workspace") @section("content")
@php
    $user = auth()->user();
    // Hanya mahasiswa pemilik TA yang bisa upload/edit/hapus.
    $canUpload = $user->id === $mahasiswaTa->user_id;
    $isOwner = $user->id === $mahasiswaTa->user_id;
    $allFiles = $grouped->flatten(1);
    $babs = $mahasiswaTa->workspaceFiles()->whereNotNull('bab')->distinct()->pluck('bab');
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-bold"><span class="material-symbols-outlined icon-md align-text-bottom">folder</span> Workspace — {{ $mahasiswaTa->mahasiswa?->name ?? "Mahasiswa" }}</h1> <a
            href="{{ route("dashboard") }}" class="px-3 py-2 rounded-md bg-bg-hover hover:bg-bg-hover text-sm">←
            Dashboard</a>
    </div> {{-- Filter --}} <form method="GET" action="{{ route("workspace.index", $mahasiswaTa) }}"
        class="bg-bg-surface rounded-xl border border-border p-4 flex flex-wrap gap-3 items-end">
        <div class="w-full sm:w-auto"> <label class="block text-xs text-text-secondary mb-1">Bab</label> <select name="bab"
                class="w-full sm:w-auto rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                <option value="">Semua Bab</option>
                @foreach ($babs as $b)
                    <option value="{{ $b }}" @selected(request("bab") === $b)>{{ $b }}</option>
                @endforeach
            </select> </div>
        <div class="w-full sm:w-auto"> <label class="block text-xs text-text-secondary mb-1">Tipe</label> <select name="type"
                class="w-full sm:w-auto rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                <option value="">Semua Tipe</option>
                <option value="pdf" @selected(request("type") === "pdf")>PDF</option>
                <option value="doc" @selected(request("type") === "doc")>DOCX</option>
                <option value="xls" @selected(request("type") === "xls")>XLSX</option>
            </select> </div>
        <div class="w-full sm:w-auto"> <label class="block text-xs text-text-secondary mb-1">Cari</label> <input type="text" name="search"
                value="{{ request("search") }}" placeholder="Nama file / catatan"
                class="w-full sm:w-auto rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
        <div class="flex gap-2 w-full sm:w-auto"> <button
                class="flex-1 sm:flex-none px-4 py-2 rounded-md bg-brand hover:bg-brand-hover text-white text-sm">Cari</button> <a
                href="{{ route("workspace.index", $mahasiswaTa) }}"
                class="flex-1 sm:flex-none px-4 py-2 rounded-md bg-bg-hover hover:bg-bg-hover text-sm text-center">Reset</a>
        </div>
    </form> {{-- Upload --}} @if ($canUpload)
        <div class="bg-bg-surface rounded-xl border border-border p-5">
            <h2 class="font-semibold mb-3">Upload File</h2>
            <form method="POST" action="{{ route("workspace.store", $mahasiswaTa) }}" enctype="multipart/form-data"
                id="upload-form"> @csrf <div class="grid sm:grid-cols-2 gap-3 mb-3">
                    <div> <label class="block text-sm font-medium mb-1">Label Bab (opsional)</label> <input
                            type="text" name="bab" placeholder="contoh: Bab 1, Bab 3 Revisi"
                            class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
                </div>
                <div id="drop-zone"
                    class="border-2 border-dashed border-border rounded-lg p-8 text-center cursor-pointer hover:border-brand/30 transition">
                    <p><span class="material-symbols-outlined" style="font-size:48px">folder_open</span></p>
                    <p class="text-sm mt-2">Tarik &amp; lepas file di sini, atau <span
                            class="text-brand font-medium">klik untuk memilih</span></p>
                    <p class="text-xs text-text-secondary mt-1">PDF, DOC, DOCX, XLS, XLSX — maks 25 MB, hingga 5 file
                    </p> <input type="file" name="files[]" id="file-input" multiple
                        accept=".pdf,.doc,.docx,.xls,.xlsx" class="hidden">
                </div>
                <div id="file-list" class="mt-3 space-y-1"></div>
                <div id="progress-wrap" class="hidden mt-3">
                    <div class="h-2 rounded-full bg-bg-panel overflow-hidden">
                        <div id="progress-bar" class="h-full rounded-full bg-brand" style="width:0%"></div>
                    </div>
                    <p id="progress-text" class="text-xs text-text-secondary mt-1">0%</p>
                </div> <button type="submit" id="upload-btn" disabled
                    class="mt-3 px-4 py-2 rounded-md bg-brand hover:bg-brand-hover text-white text-sm disabled:opacity-40">Upload</button>
                @error("files.*")
                    <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                @enderror
            </form>
        </div>
        @endif {{-- Daftar file --}} @if ($allFiles->isEmpty())
            <div class="px-4 py-8 rounded-lg bg-bg-surface border border-border text-center text-text-secondary"> Belum
                ada file di workspace. </div>
        @else
            @foreach ($grouped as $bab => $files)
                <div class="bg-bg-surface rounded-xl border border-border overflow-hidden">
                    <div class="px-4 py-3 bg-bg-panel/50 border-b border-border font-semibold text-sm">
                        {{ $bab }}</div>
                    <div class="divide-y divide-border divide-border">
                        @foreach ($files as $file)
                            @php $canModify = $user->id === $mahasiswaTa->user_id; @endphp <div class="px-4 py-3 flex items-start gap-3"> <span
                                    class="text-2xl">{{ $file->icon() }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2"> <a
                                            href="{{ $file->isPdf() ? route("workspace.preview", $file) : route("workspace.download", $file) }}"
                                            class="font-medium hover:text-brand {{ $file->isPdf() ? "" : "" }}"
                                            @if ($file->isPdf()) target="_blank" @endif>
                                            {{ $file->original_name }} </a> <span
                                            class="text-xs text-text-secondary">{{ $file->sizeHuman() }}</span> <span
                                            class="text-xs text-text-secondary">{{ $file->created_at->format("d M") }}</span>
                                    </div>
                                    @if ($file->description)
                                        <p class="text-xs text-text-secondary mt-0.5">"{{ $file->description }}"</p>
                                    @endif
                                    <p class="text-xs text-text-secondary mt-0.5">Diupload
                                        @include("partials.user-link", ["user" => $file->uploader])</p>
                                </div>
                                <div class="flex items-center gap-1 flex-shrink-0">
                                    @if ($file->isPdf())
                                        <a href="{{ route("workspace.preview", $file) }}" target="_blank"
                                            title="Preview"
                                            class="p-1.5 rounded hover:bg-bg-hover hover:bg-bg-hover"><span class="material-symbols-outlined icon-sm">visibility</span></a>
                                    @endif <a href="{{ route("workspace.download", $file) }}"
                                        title="Download" class="p-1.5 rounded hover:bg-bg-hover hover:bg-bg-hover"><span class="material-symbols-outlined icon-sm">download</span></a>
                                    @if ($canModify)
                                        <button type="button" data-edit="{{ $file->id }}"
                                            data-bab="{{ $file->bab }}" data-desc="{{ $file->description }}"
                                            class="edit-btn p-1.5 rounded hover:bg-bg-hover hover:bg-bg-hover"
                                            title="Edit"><span class="material-symbols-outlined icon-sm">edit</span></button>
                                        <form method="POST" action="{{ route("workspace.destroy", $file) }}"
                                            onsubmit="return confirm('Hapus file ini?')"> @csrf @method("DELETE")
                                            <button
                                                class="p-1.5 rounded hover:bg-status-danger/10 hover:bg-status-danger/15 text-status-danger"
                                                title="Hapus"><span class="material-symbols-outlined icon-sm">delete</span></button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
            <div class="px-4 py-2 text-xs text-text-secondary text-right"> Penyimpanan:
                {{ number_format($totalBytes / 1048576, 1) }} MB terpakai </div>
        @endif
</div> {{-- Modal edit metadata --}}
<div id="edit-modal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-bg-surface rounded-lg border border-border p-4 w-full max-w-md">
        <h3 class="font-semibold mb-3">Edit Metadata File</h3>
        <form method="POST" action="" id="edit-form"> @csrf @method("PATCH") <div class="space-y-3">
                <div> <label class="block text-sm font-medium mb-1">Label Bab</label> <input type="text"
                        name="bab" id="edit-bab"
                        class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
                <div> <label class="block text-sm font-medium mb-1">Catatan</label>
                    <textarea name="description" id="edit-desc" rows="3"
                        class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-4"> <button type="button" id="edit-cancel"
                    class="px-3 py-2 rounded-md bg-bg-panel text-sm">Batal</button> <button
                    class="px-3 py-2 rounded-md bg-brand text-white text-sm">Simpan</button> </div>
        </form>
    </div>
</div>
@endsection @section("scripts")
<script>
    // ---------- drag & drop upload ---------- (function () { var zone = document.getElementById('drop-zone'); var input = document.getElementById('file-input'); var list = document.getElementById('file-list'); var btn = document.getElementById('upload-btn'); var selectedFiles = []; function render() { list.innerHTML = ''; selectedFiles.forEach(function (f, i) { var div = document.createElement('div'); div.className = 'flex items-center justify-between text-sm px-2 py-1 rounded bg-bg-panel'; div.innerHTML = '<span class="truncate mr-2">' + f.name + ' (' + (f.size/1048576).toFixed(1) + ' MB)</span>' + '<button type="button" data-i="' + i + '" class="text-status-danger text-xs">hapus</button>'; list.appendChild(div); }); btn.disabled = selectedFiles.length === 0; } zone.addEventListener('click', function () { input.click(); }); input.addEventListener('change', function () { selectedFiles = Array.from(input.files); render(); }); ['dragover','dragenter'].forEach(function (ev) { zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('border-brand/30'); }); }); ['dragleave','drop'].forEach(function (ev) { zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.remove('border-brand/30'); }); }); zone.addEventListener('drop', function (e) { selectedFiles = Array.from(e.dataTransfer.files); render(); }); list.addEventListener('click', function (e) { if (e.target.tagName === 'BUTTON') { var i = parseInt(e.target.dataset.i, 10); selectedFiles.splice(i, 1); render(); } }); // upload dengan progress (FormData, tanpa library) document.getElementById('upload-form').addEventListener('submit', function (e) { if (selectedFiles.length === 0) { e.preventDefault(); return; } e.preventDefault(); var fd = new FormData(this); selectedFiles.forEach(function (f) { fd.append('files[]', f); }); var xhr = new XMLHttpRequest(); document.getElementById('progress-wrap').classList.remove('hidden'); xhr.upload.onprogress = function (ev) { if (ev.lengthComputable) { var pct = Math.round(ev.loaded / ev.total * 100); document.getElementById('progress-bar').style.width = pct + '%'; document.getElementById('progress-text').textContent = pct + '%'; } }; xhr.onload = function () { window.location.reload(); }; xhr.onerror = function () { alert('Upload gagal.'); document.getElementById('progress-wrap').classList.add('hidden'); }; xhr.open('POST', this.action); xhr.send(fd); }); })(); // ---------- edit metadata modal ---------- (function () { var modal = document.getElementById('edit-modal'); var form = document.getElementById('edit-form'); document.querySelectorAll('.edit-btn').forEach(function (btn) { btn.addEventListener('click', function () { var url = btn.closest('.flex').querySelector('a[href]'); // resolve route var id = btn.dataset.edit; form.action = '/workspace/files/' + id; document.getElementById('edit-bab').value = btn.dataset.bab || ''; document.getElementById('edit-desc').value = btn.dataset.desc || ''; modal.classList.remove('hidden'); }); }); document.getElementById('edit-cancel').addEventListener('click', function () { modal.classList.add('hidden'); }); modal.addEventListener('click', function (e) { if (e.target === modal) modal.classList.add('hidden'); }); })();
</script>
@endsection
