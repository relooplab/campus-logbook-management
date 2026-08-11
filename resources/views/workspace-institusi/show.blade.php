@extends('layouts.app')

@section('title', $workspace->name)

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold">{{ $workspace->name }}</h1>
            <p class="text-sm text-text-secondary">{{ $workspace->scopeLabel() }} · {{ $workspace->scopeName() }}</p>
        </div>
        <a href="{{ route('workspace-institusi.index') }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">← Kembali</a>
    </div>

    @if ($workspace->canManage($user))
        <div class="bg-bg-surface rounded-xl border border-border p-5">
            <h2 class="font-semibold mb-3">Upload File</h2>
            <form method="POST" action="{{ route('workspace-institusi.upload', $workspace) }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <input type="file" name="files[]" multiple required class="w-full text-sm">
                <input type="text" name="description" placeholder="Deskripsi (opsional)" class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                <button class="px-4 py-2 rounded-xl bg-brand hover:bg-brand-hover text-[#0b1420] text-sm">Upload</button>
            </form>
        </div>

        <div class="bg-bg-surface rounded-xl border border-border p-5">
            <h2 class="font-semibold mb-3">Pengaturan Akses</h2>
            <form method="POST" action="{{ route('workspace-institusi.access.update', $workspace) }}" class="space-y-3">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-sm mb-1">Mode Akses</label>
                    <select name="access_mode" class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                        <option value="hierarchical" @selected($workspace->access_mode === 'hierarchical')>Sesama prodi (default)</option>
                        <option value="custom" @selected($workspace->access_mode === 'custom')>Custom (dosen tertentu)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm mb-1">Dosen yang boleh akses (custom)</label>
                    <select name="allowed_user_ids[]" multiple class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                        @foreach (\App\Models\User::role('dosen')->orderBy('name')->get() as $d)
                            <option value="{{ $d->id }}" @selected($workspace->allowedUsers->contains('id', $d->id))>{{ $d->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-text-secondary mt-1">Kosongkan jika mode sesama prodi. Multi-select untuk custom.</p>
                </div>
                <button class="px-4 py-2 rounded-xl bg-brand text-[#0b1420] text-sm">Simpan Akses</button>
            </form>
        </div>
    @endif

    <div class="bg-bg-surface rounded-xl border border-border overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-text-secondary border-b border-border">
                    <th class="py-3 px-4">File</th>
                    <th class="py-3 px-4">Diunggah Oleh</th>
                    <th class="py-3 px-4">Ukuran</th>
                    <th class="py-3 px-4">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($workspace->files as $f)
                    <tr class="border-b border-border">
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined icon-md text-brand">description</span>
                                <div>
                                    <p class="font-medium">{{ $f->original_name }}</p>
                                    @if ($f->description)<p class="text-xs text-text-secondary">{{ $f->description }}</p>@endif
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4">{{ $f->uploader?->name ?? '—' }}</td>
                        <td class="py-3 px-4">{{ number_format($f->size / 1048576, 1) }} MB</td>
                        <td class="py-3 px-4 flex gap-2">
                            <a href="{{ route('workspace-institusi.files.download', [$workspace, $f]) }}" class="text-brand hover:underline text-xs">Download</a>
                            @if ($workspace->canManage($user))
                                <form method="POST" action="{{ route('workspace-institusi.files.destroy', [$workspace, $f]) }}" onsubmit="return confirm('Hapus file ini?')" class="inline">@csrf @method('DELETE')
                                    <button class="text-status-danger hover:underline text-xs">Hapus</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 px-4 text-text-secondary">Belum ada file.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection