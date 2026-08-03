<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRevisiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validasi entri revisi. Tidak ada topik/dosen, tapi wajib tanggal
     * pengiriman revisi + lampiran file perbaikan dan catatan perbaikan (PDF).
     */
    public function rules(): array
    {
        return [
            'parent_entry_id' => [
                'required',
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
            'lampiran' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'catatan_perbaikan' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal_pengiriman.required' => 'Tanggal pengiriman revisi wajib diisi.',
            'tanggal_pengiriman.before_or_equal' => 'Tanggal tidak boleh di masa depan.',
            'parent_entry_id.required' => 'Entri asal revisi wajib dipilih.',
            'progres_kendala.required' => 'Ringkasan perbaikan wajib diisi.',
            'lampiran.required' => 'File perbaikan (PDF) wajib diunggah.',
            'lampiran.mimes' => 'File perbaikan harus berupa PDF.',
            'lampiran.max' => 'File perbaikan maksimal 10 MB.',
            'catatan_perbaikan.required' => 'Catatan perbaikan (PDF) wajib diunggah.',
            'catatan_perbaikan.mimes' => 'Catatan perbaikan harus berupa PDF.',
            'catatan_perbaikan.max' => 'Catatan perbaikan maksimal 10 MB.',
        ];
    }
}
