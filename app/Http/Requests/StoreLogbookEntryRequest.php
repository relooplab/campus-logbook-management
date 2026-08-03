<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLogbookEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validasi entri logbook (sesi bimbingan biasa).
     */
    public function rules(): array
    {
        return [
            'tanggal_bimbingan' => ['required', 'date', 'before_or_equal:today'],
            'topik' => ['required', 'string', 'max:255'],
            'progres_kendala' => ['required', 'string'],
            'lampiran' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal_bimbingan.required' => 'Tanggal bimbingan wajib diisi.',
            'tanggal_bimbingan.before_or_equal' => 'Tanggal tidak boleh di masa depan.',
            'topik.required' => 'Topik bimbingan wajib diisi.',
            'progres_kendala.required' => 'Ringkasan perbaikan wajib diisi.',
            'lampiran.mimes' => 'Lampiran harus berupa file PDF.',
            'lampiran.max' => 'Ukuran lampiran maksimal 10 MB.',
        ];
    }
}
