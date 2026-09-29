{{--
    Kontrol unggah file bergaya aplikasi.
    <input type="file"> asli tetap satu-satunya sumber state (MIME, ukuran,
    validasi server) — hanya tampilan native-nya yang disembunyikan.
--}}
@props([
    'input' => 'lampiran',
    'title' => 'Upload Lampiran',
    'hint' => '',
    'accept' => '',
    'maxMb' => 10,
    'types' => ['pdf'],
    'required' => false,
    'icon' => 'cloud_upload',
])

<div class="upload-field" data-upload="{{ $input }}" data-max-mb="{{ $maxMb }}" data-types='@json($types)'>
    <input type="file" name="{{ $input }}" id="{{ $input }}" accept="{{ $accept }}" class="sr-only"
        data-upload-input @required($required) aria-describedby="{{ $input }}-file-hint">

    <div class="upload-row" data-upload-empty>
        <span class="icon-chip h-11 w-11" aria-hidden="true">
            <span class="material-symbols-outlined icon-md text-brand">{{ $icon }}</span>
        </span>
        <div class="upload-row-text">
            <p class="text-sm font-medium text-text-primary">{{ $title }}</p>
            <p class="text-caption text-text-secondary" id="{{ $input }}-file-hint">
                {{ $hint }}@if ($required) <span aria-hidden="true">•</span> Wajib diunggah @endif
            </p>
        </div>
        <button type="button" class="btn-secondary inline-flex items-center gap-2 px-4 py-2 text-sm"
            data-upload-pick="{{ $input }}">
            <span class="material-symbols-outlined icon-sm" aria-hidden="true">attach_file</span> Pilih File
        </button>
    </div>

    <div class="upload-row hidden" data-upload-selected>
        <span class="icon-chip h-11 w-11" aria-hidden="true">
            <span class="material-symbols-outlined icon-md text-brand">description</span>
        </span>
        <div class="upload-row-text">
            <p class="break-all text-sm font-medium text-text-primary" data-upload-name></p>
            <p class="text-caption text-text-secondary" data-upload-size></p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="btn-secondary px-3 py-1.5 text-xs" data-upload-pick="{{ $input }}">Ganti</button>
            <button type="button" class="btn-ghost px-3 py-1.5 text-xs" data-upload-clear="{{ $input }}">Hapus</button>
        </div>
    </div>

    <p class="mt-2 hidden text-xs text-status-danger" data-upload-error role="alert"></p>

    @error($input)
        <p class="mt-2 text-xs text-status-danger">{{ $message }}</p>
    @enderror
</div>
