<?php

namespace App\Http\Requests;

use App\Models\Institution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRevisiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validasi entri revisi. Mahasiswa dapat membuat entri revisi tanpa harus
     * ada logbook terlebih dahulu (parent_entry_id opsional). Ukuran & jenis
     * file upload diatur admin (institution settings).
     */
    public function rules(): array
    {
        $inst = Institution::active();
        $maxKb = $inst->maxUploadSizeMb() * 1024;
        $mimes = implode(',', $inst->allowedFileTypes());

        return [
            'parent_entry_id' => [
                'nullable',
                'integer',
                Rule::exists('logbook_entries', 'id')->where(function ($query) {
                    $query->where('mahasiswa_ta_id', $this->user()?->mahasiswaTa?->id)
                        ->where('status', 'revisi');
                }),
            ],
            'addressed_comment_ids' => ['nullable', 'array'],
            'addressed_comment_ids.*' => ['integer', 'distinct'],
            'tanggal_pengiriman' => ['required', 'date', 'before_or_equal:today'],
            'progres_kendala' => ['required', 'string'],
            'lampiran' => ['required', 'file', 'mimes:'.$mimes, 'max:'.$maxKb],
            'catatan_perbaikan' => ['required', 'file', 'mimes:'.$mimes, 'max:'.$maxKb],
        ];
    }

    public function messages(): array
    {
        $inst = Institution::active();
        $maxMb = $inst->maxUploadSizeMb();
        $types = strtoupper(implode(', ', $inst->allowedFileTypes()));

        return [
            'tanggal_pengiriman.required' => 'Tanggal pengiriman revisi wajib diisi.',
            'tanggal_pengiriman.before_or_equal' => 'Tanggal tidak boleh di masa depan.',
            'parent_entry_id.required' => 'Entri asal revisi wajib dipilih.',
            'progres_kendala.required' => 'Ringkasan perbaikan wajib diisi.',
            'lampiran.required' => 'File perbaikan wajib diunggah.',
            'lampiran.mimes' => 'File perbaikan harus berupa file '.$types.'.',
            'lampiran.max' => 'File perbaikan maksimal '.$maxMb.' MB.',
            'catatan_perbaikan.required' => 'Catatan perbaikan wajib diunggah.',
            'catatan_perbaikan.mimes' => 'Catatan perbaikan harus berupa file '.$types.'.',
            'catatan_perbaikan.max' => 'Catatan perbaikan maksimal '.$maxMb.' MB.',
        ];
    }
}